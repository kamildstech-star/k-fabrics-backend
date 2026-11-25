<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Services\OrderLifecycle;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(OrderLifecycle::class)->updateDetails($record, $data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('printTailorSheet')
                ->label('Print Tailor Sheet')
                ->icon('heroicon-o-printer')
                ->url(fn () => route('admin.orders.tailor-sheet', ['order' => $this->record]))
                ->openUrlInNewTab(),
            Actions\Action::make('confirmOrder')
                ->label('Confirm order')
                ->visible(fn (): bool => $this->record->order_status === 'pending')
                ->requiresConfirmation()
                ->modalDescription('Confirm the customer’s order. For COD, verify the order with the customer first.')
                ->action(fn () => $this->perform(fn () => app(OrderLifecycle::class)->confirm(
                    $this->record, $this->record->payment_method === 'cod' ? 'manual_call' : 'advance_paid'
                ))),
            Actions\Action::make('recordPayment')
                ->label('Record verified payment / refund')
                ->form([
                    Forms\Components\Select::make('kind')->options(['receipt' => 'Payment received', 'refund' => 'Refund issued'])->required(),
                    Forms\Components\TextInput::make('amount')->label('Amount (PKR)')->prefix('Rs.')->numeric()->minValue(0.01)->maxValue(99999999.99)->required(),
                    Forms\Components\TextInput::make('reference')->maxLength(120)->required(),
                    Forms\Components\DateTimePicker::make('received_at')->label('Transaction time')->default(now())->maxDate(now())->required(),
                ])
                ->action(fn (array $data) => $this->perform(fn () => app(OrderLifecycle::class)->recordPayment(
                    $this->record, $data['kind'], (string) $data['amount'], $data['reference'], Carbon::parse($data['received_at']), auth()->user()
                ))),
            Actions\Action::make('advanceOrder')
                ->label('Update fulfillment')
                ->modalDescription(fn (): ?string => $this->record->order_status === 'packed'
                    ? 'Save the courier name and consignment ID on this order before marking it dispatched.' : null)
                ->visible(fn (): bool => in_array($this->record->order_status, ['confirmed', 'stitching', 'packed', 'shipped', 'delivered'], true))
                ->form([
                    Forms\Components\Select::make('status')->options(fn () => match ($this->record->order_status) {
                        'confirmed', 'stitching' => ['packed' => 'Packed'],
                        'packed' => ['shipped' => 'Dispatched'],
                        'shipped' => ['delivered' => 'Delivered', 'returned' => 'Returned'],
                        'delivered' => ['returned' => 'Returned'],
                        default => [],
                    })->required(),
                ])
                ->action(fn (array $data) => $this->perform(fn () => app(OrderLifecycle::class)->advanceOrder($this->record, $data['status']))),
            Actions\Action::make('cancelOrder')
                ->label('Cancel order')
                ->color('danger')
                ->visible(fn (): bool => in_array($this->record->order_status, ['pending', 'confirmed', 'packed'], true))
                ->form([Forms\Components\Textarea::make('reason')->maxLength(255)->required()])
                ->action(fn (array $data) => $this->perform(fn () => app(OrderLifecycle::class)->cancel($this->record, $data['reason'], 'staff'))),
        ];
    }

    private function perform(callable $action): void
    {
        try {
            $action();
            $this->record->refresh();
            $this->refreshFormData(['order_status', 'payment_status', 'confirmation_channel', 'confirmed_at', 'cancelled_reason']);
            Notification::make()->title('Order updated')->success()->send();
        } catch (ValidationException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        }
    }
}
