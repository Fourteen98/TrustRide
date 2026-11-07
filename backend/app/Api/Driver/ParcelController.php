<?php

namespace App\Api\Driver;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Deliveries\Actions\ConfirmDelivery;
use Modules\Deliveries\Actions\ConfirmPickup;
use Modules\Deliveries\Actions\FailDelivery;
use Modules\Deliveries\Actions\FinishRun;
use Modules\Deliveries\Actions\StartRun;
use Modules\Deliveries\Enums\RunStatus;
use Modules\Deliveries\Models\Business;
use Modules\Deliveries\Models\Delivery;
use Modules\Deliveries\Models\DeliveryRun;
use Modules\Deliveries\Queries\RunSheet;
use Modules\Drivers\Models\Driver;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Aveo Ox in the driver's app: his parcel runs. Start the run, collect at each business, then at each door
 * type the customer's code, or say why it couldn't be delivered. A driver only ever sees his own runs.
 */
class ParcelController
{
    public function __construct(private RunSheet $sheet) {}

    /**
     * His parcel runs: today's, and any still out from before.
     *
     * Each run lists the businesses to collect from, then every drop in order with the customer's phone to call.
     */
    public function today(Request $request): JsonResponse
    {
        $runs = DeliveryRun::query()
            ->where('driver_id', $this->driver($request)->id)
            ->where(fn ($query) => $query->whereDate('date', today())->orWhere('status', RunStatus::Out))
            ->orderBy('date')->orderBy('id')
            ->get();

        return response()->json([
            /** @var list<array{run_id: int, zone: string, date: string, status: 'planned'|'out'|'done', pickups: list<array{business_id: int, name: string, address: string, landmark: string|null, phone: string, parcels: int, collected: bool}>, drops: list<array{delivery_id: int, position: int, business: string, reference: string|null, customer_name: string, customer_phone: string, address: string, landmark: string|null, notes: string|null, status: 'pending'|'assigned'|'picked_up'|'delivered'|'failed'|'cancelled', failure_reason: string|null}>, counts: array{total: int, delivered: int, failed: int, to_collect: int, with_driver: int}}> */
            'runs' => $runs->map(fn (DeliveryRun $run) => $this->sheet->handle($run))->values()->all(),
        ]);
    }

    /** Set off on a run. Then he can collect parcels at each business. */
    public function start(Request $request, StartRun $start): JsonResponse
    {
        $request->validate(['run_id' => ['required', 'integer']]);
        $run = $this->run($request, $request->integer('run_id'));
        $start->handle($run);

        return $this->reply($run);
    }

    /**
     * He has collected a business's parcels.
     *
     * Each customer is texted that hers is on its way, with the code to give him at the door.
     */
    public function collect(Request $request, ConfirmPickup $pickup): JsonResponse
    {
        $request->validate(['run_id' => ['required', 'integer'], 'business_id' => ['required', 'integer']]);
        $run = $this->run($request, $request->integer('run_id'));
        $pickup->handle($run, Business::query()->findOrFail($request->integer('business_id')));

        return $this->reply($run);
    }

    /**
     * The customer has her parcel: the code she gave him.
     *
     * A wrong code says how many tries are left. After the last, he calls the customer or Aveo.
     */
    public function deliver(Request $request, ConfirmDelivery $confirm): JsonResponse
    {
        $request->validate(['delivery_id' => ['required', 'integer'], 'code' => ['required', 'string', 'max:10']]);
        $delivery = $this->delivery($request);
        $confirm->handle($delivery, $request->string('code')->toString());

        return $this->reply($delivery->run);
    }

    /** He couldn't hand it over. The customer is told why. */
    public function notDelivered(Request $request, FailDelivery $fail): JsonResponse
    {
        $request->validate(['delivery_id' => ['required', 'integer'], 'reason' => ['required', 'string', 'max:250']]);
        $delivery = $this->delivery($request);
        $fail->handle($delivery, $request->string('reason')->toString());

        return $this->reply($delivery->run);
    }

    /**
     * The run is over.
     *
     * Every collected parcel must be marked first. Any he never collected go back in the queue for tomorrow.
     */
    public function finish(Request $request, FinishRun $finish): JsonResponse
    {
        $request->validate(['run_id' => ['required', 'integer']]);
        $run = $this->run($request, $request->integer('run_id'));
        $finish->handle($run);

        return $this->reply($run);
    }

    private function reply(DeliveryRun $run): JsonResponse
    {
        return response()->json([
            /** @var array{run_id: int, zone: string, date: string, status: 'planned'|'out'|'done', pickups: list<array{business_id: int, name: string, address: string, landmark: string|null, phone: string, parcels: int, collected: bool}>, drops: list<array{delivery_id: int, position: int, business: string, reference: string|null, customer_name: string, customer_phone: string, address: string, landmark: string|null, notes: string|null, status: 'pending'|'assigned'|'picked_up'|'delivered'|'failed'|'cancelled', failure_reason: string|null}>, counts: array{total: int, delivered: int, failed: int, to_collect: int, with_driver: int}} */
            'run' => $this->sheet->handle($run->fresh()),
        ]);
    }

    private function driver(Request $request): Driver
    {
        return Driver::query()->where('account_id', $request->user()->id)->first()
            ?? throw new HttpException(403, 'This account is not a driver.');
    }

    private function run(Request $request, int $id): DeliveryRun
    {
        return DeliveryRun::query()->where('driver_id', $this->driver($request)->id)->findOrFail($id);
    }

    private function delivery(Request $request): Delivery
    {
        $driver = $this->driver($request);

        return Delivery::query()->whereHas('run', fn ($run) => $run->where('driver_id', $driver->id))
            ->findOrFail($request->integer('delivery_id'));
    }
}
