<?php

namespace Modules\Messaging\Filament\Resources;

use App\Support\Filament\GatedByPermission;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Messaging\Channels\ChannelManager;
use Modules\Messaging\Filament\Resources\MessageRuleResource\Pages\ManageMessageRules;
use Modules\Messaging\Models\EventLog;
use Modules\Messaging\Models\MessageRule;
use UnitEnum;

/** The rules: when this event happens, tell these people, on this channel, with these words. */
class MessageRuleResource extends Resource
{
    use GatedByPermission;

    protected static string $viewPermission = 'messaging.log.view';

    protected static string $managePermission = 'messaging.rules.manage';

    protected static ?string $model = MessageRule::class;

    protected static ?string $modelLabel = 'message rule';

    protected static ?string $slug = 'message-rules';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static string|UnitEnum|null $navigationGroup = 'Messaging';

    protected static ?int $navigationSort = 2;

    /** Event names to offer: the ones modules are known to fire, plus any that have actually happened. */
    public static function eventOptions(): array
    {
        $known = config('messaging.known_events', []);
        $seen = EventLog::query()->distinct()->pluck('name')->mapWithKeys(fn (string $name) => [$name => $name])->all();

        return collect($seen)->merge($known)->map(fn (string $description, string $name) => $name === $description ? $name : "{$name}: {$description}")->sortKeys()->all();
    }

    /** The placeholders the most recent event of this name carried, so the person writing knows what is on offer. */
    public static function placeholdersFor(?string $eventName): string
    {
        if (! $eventName) {
            return 'Choose an event to see what you can put in the message.';
        }

        $payload = EventLog::query()->where('name', $eventName)->latest('id')->value('payload');

        if (! $payload) {
            return 'This event has not happened yet, so its placeholders can\'t be listed. {name} always works.';
        }

        $keys = collect($payload)->filter(fn ($value) => is_scalar($value) || $value === null)->keys()->push('name')->unique();

        return 'You can use: '.$keys->map(fn (string $key) => '{'.$key.'}')->join(' ');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(80)->helperText('For your own reference.'),
            Select::make('event_name')->label('When this happens')->required()->searchable()->live()
                ->options(fn () => static::eventOptions()),
            Select::make('audience')->label('Tell')->required()->default(MessageRule::EVERYONE)
                ->options([
                    MessageRule::EVERYONE => 'Everyone the event concerns',
                    'rider' => 'The rider',
                    'driver' => 'The driver',
                    'mate' => 'The mate',
                ]),
            Select::make('channel')->label('On')->required()->default(MessageRule::PREFERRED)
                ->options([
                    MessageRule::PREFERRED => 'Their preferred channel, then the others if it fails',
                    'sms' => 'SMS only',
                    'whatsapp' => 'WhatsApp only',
                    'push' => 'App notification only',
                ])
                ->helperText(fn () => 'Set up now: '.(collect(ChannelManager::CHANNELS)->filter(fn ($key) => app(ChannelManager::class)->isSetUp($key))->join(', ') ?: 'none')),
            Textarea::make('body')->label('Say')->required()->rows(4)->maxLength(600)->columnSpanFull()
                ->helperText(fn (Get $get) => static::placeholdersFor($get('event_name'))),
            Toggle::make('is_active')->label('Switched on')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('event_name')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('event_name')->label('When')->badge()->searchable()->sortable(),
                TextColumn::make('audience')->label('Tell')->formatStateUsing(fn (string $state) => $state === MessageRule::EVERYONE ? 'Everyone' : ucfirst($state)),
                TextColumn::make('channel')->label('On')->formatStateUsing(fn (string $state) => $state === MessageRule::PREFERRED ? 'Preferred' : strtoupper($state)),
                TextColumn::make('body')->label('Say')->limit(60)->wrap(),
                TextColumn::make('messages_count')->counts('messages')->label('Sent'),
                IconColumn::make('is_active')->label('On')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageMessageRules::route('/')];
    }
}
