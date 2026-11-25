<?php

namespace App\Filament\Platform\Resources;

use App\Core\Audit\Audit;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Tenancy;
use App\Filament\Platform\Resources\TenantResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class TenantResource extends Resource
{
    public static function getNavigationLabel(): string
    {
        return __('Casinos');
    }

    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';


    public static function canAccess(): bool
    {
        return \App\Filament\Platform\Pages\PlatformAddonsPage::canAccess();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('Casino'))->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(120),
                Forms\Components\TextInput::make('slug')->required()->maxLength(60)->alphaDash()->unique(ignoreRecord: true),
                Forms\Components\Select::make('status')->options([Tenant::ACTIVE => __('Active'), Tenant::SUSPENDED => __('Suspended')])->required()->default(Tenant::ACTIVE),
                Forms\Components\TextInput::make('plan')->maxLength(60),
            ])->columns(2),
            Forms\Components\Section::make(__('Domains'))->schema([
                Forms\Components\Repeater::make('domains')->relationship()->schema([
                    Forms\Components\TextInput::make('domain')->required()->maxLength(191)
                        ->regex('/^(?=.{1,191}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i')
                        ->dehydrateStateUsing(fn ($state) => strtolower(trim((string) $state)))
                        ->unique(table: 'tenant_domains', column: 'domain', ignoreRecord: true),
                    Forms\Components\Toggle::make('is_primary'),
                ])->columns(2)->defaultItems(1),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('slug'),
                Tables\Columns\TextColumn::make('domains.domain')->badge(),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state) => $state === Tenant::ACTIVE ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle_status')
                    ->label(fn (Tenant $t) => $t->isActive() ? 'Suspend' : 'Activate')
                    ->color(fn (Tenant $t) => $t->isActive() ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (Tenant $t) {
                        $t->update(['status' => $t->isActive() ? Tenant::SUSPENDED : Tenant::ACTIVE]);
                        Audit::record('tenant.status_changed', 'tenant', $t->id, ['status' => $t->status]);
                    }),
                Tables\Actions\Action::make('create_admin')
                    ->label(__('Create admin'))
                    ->icon('heroicon-o-user-plus')
                    ->form([
                        Forms\Components\TextInput::make('name')->required(),
                        Forms\Components\TextInput::make('email')->email()->required(),
                        Forms\Components\TextInput::make('password')->password()->required()->minLength(12),
                    ])
                    ->action(fn (Tenant $t, array $data) => self::createTenantAdmin($t, $data)),
            ]);
    }

    /** Creates an administrator inside the tenant (runs in that tenant's context). */
    public static function createTenantAdmin(Tenant $tenant, array $data): ?User
    {
        $user = Tenancy::runAs($tenant, function () use ($data) {
            if (User::where('email', $data['email'])->exists()) {
                return null;
            }

            return DB::transaction(function () use ($data) {
                $user = new User();
                $user->forceFill(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'role_id' => 0])->save();
                $user->assignRole(Role::findOrCreate('admin', 'web'));

                return $user;
            });
        });

        $user
            ? Notification::make()->title(__('Administrator created'))->success()->send()
            : Notification::make()->title(__('A user with this e-mail already exists in this casino'))->danger()->send();

        if ($user) {
            Audit::record('tenant.admin_created', 'tenant', $tenant->id, ['user_id' => $user->id]);
        }

        return $user;
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit'   => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
