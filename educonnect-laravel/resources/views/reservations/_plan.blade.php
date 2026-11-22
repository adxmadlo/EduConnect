{{-- Pastilles des salles, groupées par étage. Sur la page réservations elles
     sont cliquables : le clic pré-remplit la demande avec la salle et le
     créneau affiché par le plan. --}}
<div class="rooms-plan" @if ($interactif ?? false) data-interactif="1" @endif>
    @foreach ($planEtages as $etage)
        <div class="rooms-floor">
            <div class="rooms-floor__title">
                Étage {{ $etage['etage'] }}
                <span>{{ $etage['libres'] }}/{{ $etage['total'] }} libres</span>
            </div>
            <div class="rooms-floor__rooms">
                @foreach ($etage['salles'] as $salle)
                    @php
                        $classe = 'room-chip ' . ($salle['occupee'] ? 'room-chip--busy' : 'room-chip--free');
                        $titre = 'Salle ' . $salle['nom'] . ' — ' . ucfirst($salle['type']) . ' — ' . $salle['capacite'] . ' places';
                    @endphp
                    @if ($interactif ?? false)
                        <button type="button" class="{{ $classe }}"
                                title="{{ $titre }}{{ $salle['occupee'] ? ' — occupée sur ce créneau' : ' — cliquer pour pré-remplir la demande' }}"
                                data-salle="{{ $salle['id'] }}" data-nom="{{ $salle['nom'] }}"
                                data-capacite="{{ $salle['capacite'] }}" @disabled($salle['occupee'])>
                            <span class="room-chip__num">{{ $salle['libelle'] }}</span>
                            <span class="room-chip__state">{{ $salle['occupee'] ? 'Occupée' : 'Libre' }}</span>
                        </button>
                    @else
                        <span class="{{ $classe }}" title="{{ $titre }}">
                            <span class="room-chip__num">{{ $salle['libelle'] }}</span>
                            <span class="room-chip__state">{{ $salle['occupee'] ? 'Occupée' : 'Libre' }}</span>
                        </span>
                    @endif
                @endforeach
            </div>
        </div>
    @endforeach
</div>
