<?php

namespace Modules\Deliveries\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Access\Models\Staff;
use Modules\Deliveries\Models\Business;
use Modules\Deliveries\Models\Zone;

/**
 * Many parcels at once from a spreadsheet saved as CSV. The first row names the columns:
 * customer_name, customer_phone, zone, address, and optionally landmark, notes and reference.
 * The zone is its name, as in the console.
 *
 * All or nothing: every row is checked first, and if any is wrong none is added and the wrong rows are listed.
 */
class ImportDeliveries
{
    public const REQUIRED = ['customer_name', 'customer_phone', 'zone', 'address'];

    public const OPTIONAL = ['landmark', 'notes', 'reference'];

    public function __construct(private CreateDelivery $create) {}

    /** @return int how many parcels were added */
    public function handle(Business $business, string $csv, ?Staff $by = null): int
    {
        $rows = $this->rows($csv);
        $zones = Zone::query()->where('is_active', true)->get()->keyBy(fn (Zone $zone) => mb_strtolower($zone->name));
        $errors = [];

        foreach ($rows as $line => &$row) {
            $zone = $zones[mb_strtolower(trim($row['zone'] ?? ''))] ?? null;

            if (! $zone) {
                $errors[] = "Row {$line}: no zone called \"{$row['zone']}\".";

                continue;
            }

            $row['zone_id'] = $zone->id;

            try {
                $this->create->check($business, $row);
            } catch (DomainException $e) {
                $errors[] = "Row {$line}: {$e->getMessage()}";
            }
        }
        unset($row);

        if ($errors !== []) {
            $more = count($errors) > 5 ? ' And '.(count($errors) - 5).' more.' : '';

            throw new DomainException('Nothing was added. '.implode(' ', array_slice($errors, 0, 5)).$more);
        }

        // The allowance, for all of them together.
        $this->create->check($business, reset($rows), count($rows));

        return DB::transaction(function () use ($business, $rows, $by) {
            foreach ($rows as $row) {
                $this->create->handle($business, $row, $by);
            }

            return count($rows);
        });
    }

    /** @return array<int, array<string, string>> keyed by the row's line number in the file */
    private function rows(string $csv): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim(preg_replace('/^\xEF\xBB\xBF/', '', $csv)));
        $header = array_map(fn ($name) => mb_strtolower(trim(str_replace(' ', '_', $name))), str_getcsv(array_shift($lines) ?? ''));

        if ($missing = array_diff(self::REQUIRED, $header)) {
            throw new DomainException('The first row must name the columns. Missing: '.implode(', ', $missing).'.');
        }

        $rows = [];

        foreach ($lines as $index => $line) {
            if (trim($line, " ,\t") === '') {
                continue;
            }

            $values = array_pad(str_getcsv($line), count($header), '');
            $rows[$index + 2] = array_intersect_key(array_combine($header, array_slice($values, 0, count($header))), array_flip([...self::REQUIRED, ...self::OPTIONAL]));
        }

        if ($rows === []) {
            throw new DomainException('The file has no parcels in it.');
        }

        return $rows;
    }
}
