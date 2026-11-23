<?php

namespace App\Filament\Admin\Pages;

use App\Livewire\LatestPixPayments;
use App\Models\User;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class DigitoPayPaymentPage extends Page
{

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static string $view = 'filament.pages.digitopay-payment-page';

    protected static ?string $navigationLabel = 'DigitoPay Pagamentos';

    protected static ?string $modelLabel = 'DigitoPay Pagamentos';

    protected static ?string $title = 'DigitoPay Pagamentos';

    protected static ?string $slug = 'digitopay-pagamentos';

    /**
     * @dev @victormsalatiel
     * @return bool
     */
    public static function canAccess(): bool
    {
        // Only when the gateway addon is enabled; the page never references addon classes directly.
        return auth()->user()->hasRole('admin') && self::gateway() !== null;
    }

    private static function gateway(): ?\App\Core\Payments\Contracts\SupportsManualPayout
    {
        if (! \App\Core\Payments\PaymentGateways::has('digitopay')) {
            return null;
        }
        $gateway = \App\Core\Payments\PaymentGateways::get('digitopay');

        return $gateway instanceof \App\Core\Payments\Contracts\SupportsManualPayout ? $gateway : null;
    }

    public ?array $data = [];

    /**
     * @return void
     */
    public function mount(): void
    {
        $this->form->fill();
    }

    /**
     * @param Form $form
     * @return Form
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Detalhes de Pagamento')
                    ->schema([
                        Select::make('user_id')
                            ->label('Usuários')
                            ->placeholder('Selecione um usuário')
                            ->relationship(name: 'user', titleAttribute: 'name')
                            ->options(
                                fn($get) => User::query()
                                    ->pluck('name', 'id')
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required(),
                        TextInput::make('pix_key')
                            ->label('Chave Pix')
                            ->placeholder('Digite a chave Pix')
                            ->required(),
                        Select::make('pix_type')
                            ->label('Tipo de Chave')
                            ->placeholder('Selecione o tipo de chave')
                            ->options([
                                'document' => 'Documento',
                                'phoneNumber' => 'Telefone',
                                'randomKey' => 'Chave aleatória',
                                'paymentCode' => 'Código de pagamento',
                            ]),
                        TextInput::make('amount')
                            ->label('Valor')
                            ->placeholder('Digite um valor')
                            ->required()
                            ->numeric(),
                        Textarea::make('observation')
                            ->label('Observação')
                            ->placeholder('Deixe uma observação caso tenha')
                            ->rows(5)
                            ->cols(10)
                            ->columnSpanFull()
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    /**
     * @return void
     */
    public function submit(): void
    {
        $gateway = self::gateway();
        if (! $gateway) {
            Notification::make()->title('Gateway unavailable')->danger()->send();
            return;
        }

        $data = [
            'user_id'     => $this->data['user_id'] ?? null,
            'pix_key'     => (string) ($this->data['pix_key'] ?? ''),
            'pix_type'    => (string) ($this->data['pix_type'] ?? ''),
            'amount'      => (string) \App\Core\Money\Amount::normalize((string) ($this->data['amount'] ?? '')),
            'observation' => $this->data['observation'] ?? null,
        ];

        if ($data['pix_key'] === '' || $data['amount'] === '' || \App\Core\Money\Amount::toMinor($data['amount']) <= 0) {
            Notification::make()->title('Invalid payout')->body('Key and a positive amount are required.')->danger()->send();
            return;
        }

        $ok = $gateway->manualPayout($data);
        \App\Core\Audit\Audit::record('payout.manual', 'gateway', 'digitopay', [
            'amount' => $data['amount'], 'pix_type' => $data['pix_type'], 'user_id' => $data['user_id'], 'accepted' => $ok,
        ]);

        $ok
            ? Notification::make()->title('Payout requested')->success()->send()
            : Notification::make()->title('Payout failed')->body('The gateway rejected the payout.')->danger()->send();
    }

    /**
     * @return array|\Filament\Widgets\WidgetConfiguration[]|string[]
     */
    public function getFooterWidgets(): array
    {
        return [
            LatestPixPayments::class
        ];
    }
}
