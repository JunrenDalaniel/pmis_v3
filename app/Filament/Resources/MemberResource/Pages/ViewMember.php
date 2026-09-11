<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMember extends ViewRecord
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('print')
                ->label('Print Profile & History')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->alpineClickHandler(function ($record) {
                    $printUrl = route('members.print', ['record' => $record]);
                    return "(() => {
                        let f = document.getElementById('pmis_print_frame');
                        if (!f) {
                            f = document.createElement('iframe');
                            f.id = 'pmis_print_frame';
                            f.style.position = 'fixed';
                            f.style.right = '0';
                            f.style.bottom = '0';
                            f.style.width = '0';
                            f.style.height = '0';
                            f.style.border = '0';
                            document.body.appendChild(f);
                        }
                        f.onload = () => {
                            f.onload = null;
                            try {
                                f.contentWindow.focus();
                                f.contentWindow.print();
                            } catch (e) {
                                console.error('Print error:', e);
                            }
                        };
                        f.src = '{$printUrl}' + (('{$printUrl}'.indexOf('?') !== -1 ? '&' : '?') + '_t=' + Date.now());
                    })()";
                }),
            EditAction::make(),
        ];
    }

    public function getRelationManagers(): array
    {
        return [
            MemberResource\RelationManagers\LoanApplicationsRelationManager::class,
        ];
    }
}
