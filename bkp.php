Forms\Components\Select::make('seller_id')
                                    ->label('Vendedor Responsável')
                                    ->options(function () {
                                        $tenant = \Filament\Facades\Filament::getTenant();

                                        // 1. Isola por Tenant (Segurança Máxima)
                                        $query = \App\Models\Seller::with('user')
                                            ->where('tenant_id', $tenant?->id);

                                        /** @var \App\Models\User $user */
                                        $user = \Filament\Facades\Filament::auth()->user();

                                        // 2. Se for vendedor restrito, mostra só ele mesmo na lista
                                        if ($user && $user->hasPermissionTo('view_my_leads')) {
                                            $query->where('id', $user->seller?->id);
                                        }

                                        return $query->get()->pluck('user.name', 'id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->default(function () {
                                        /** @var \App\Models\User $user */
                                        $user = \Filament\Facades\Filament::auth()->user();
                                        return $user->seller?->id;
                                    })
                                    ->disabled(function () {
                                        /** @var \App\Models\User $user */
                                        $user = \Filament\Facades\Filament::auth()->user();
                                        return $user->hasPermissionTo('view_my_leads');
                                    })
                                    ->dehydrated(),