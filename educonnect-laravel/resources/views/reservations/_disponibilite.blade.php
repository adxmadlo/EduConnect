{{-- Plan des étages, graphique et sélecteur de créneau (étudiant et administration) --}}
<section class="card" style="margin-bottom:16px">
    <div class="card__head">
        <h2 class="card__title"><i class="bi bi-buildings" aria-hidden="true"></i>Disponibilité des salles</h2>
        <span class="muted" id="roomsResume" aria-live="polite">
            {{ $totalLibres }} salle{{ $totalLibres > 1 ? 's' : '' }} libre{{ $totalLibres > 1 ? 's' : '' }}
            sur {{ $totalSalles }}
        </span>
    </div>
    <div class="card__body">
        <div class="rooms-toolbar">
            <label for="creneauSalles">Occupation au créneau</label>
            <input type="datetime-local" id="creneauSalles" name="creneau"
                   value="{{ $debutCreneau->format('Y-m-d\TH:i') }}" step="900">
            <span class="muted" id="roomsCreneau">Créneau de 1 h · {{ $debutCreneau->format('d/m/Y H:i') }} → {{ $finCreneau->format('H:i') }}</span>
        </div>

        <div class="rooms-layout">
            <div class="rooms-chart">
                <canvas id="graphiqueSalles" height="240" role="img"
                        aria-label="Répartition des salles libres et occupées par étage"></canvas>
            </div>

            <div id="planSalles">
                @include('reservations._plan', ['planEtages' => $planEtages, 'interactif' => $interactif ?? false])
            </div>
        </div>

        <p class="rooms-legend">
            <span><i class="dot dot--free"></i>Libre</span>
            <span><i class="dot dot--busy"></i>Occupée par un cours ou une réservation acceptée</span>
        </p>
        @if ($interactif ?? false)
            <p class="rooms-hint">
                <i class="bi bi-cursor" aria-hidden="true"></i>
                Cliquez sur une salle libre pour pré-remplir la demande avec le créneau affiché.
            </p>
        @endif
    </div>
</section>

@once
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script>
            // Plan et graphique de disponibilité : recalculés sur le créneau choisi,
            // sans rechargement de la page. Les pastilles libres pré-remplissent la
            // demande de réservation (salle, début et fin).
            (function () {
                const canvas = document.getElementById('graphiqueSalles');
                const conteneurPlan = document.getElementById('planSalles');
                const selecteur = document.getElementById('creneauSalles');
                const resume = document.getElementById('roomsResume');
                const libelleCreneau = document.getElementById('roomsCreneau');

                if (!conteneurPlan || !selecteur) {
                    return;
                }

                const urlOccupation = @json(route('reservations.occupation'));
                const interactif = {{ ($interactif ?? false) ? 'true' : 'false' }};
                let etages = @json($planEtages);
                let creneau = {
                    debutLocal: @json($debutCreneau->format('Y-m-d\TH:i')),
                    finLocal: @json($finCreneau->format('Y-m-d\TH:i')),
                    debutLisible: @json($debutCreneau->format('d/m/Y H:i')),
                    finLisible: @json($finCreneau->format('H:i')),
                };

                // ── Graphique ────────────────────────────────────────────────
                const configuration = function () {
                    return {
                        type: 'bar',
                        data: {
                            labels: etages.map(e => 'Étage ' + e.etage),
                            datasets: [
                                {
                                    label: 'Libres',
                                    data: etages.map(e => e.libres),
                                    backgroundColor: '#326ea9',
                                    borderRadius: 5,
                                    stack: 'salles',
                                },
                                {
                                    label: 'Occupées',
                                    data: etages.map(e => e.total - e.libres),
                                    backgroundColor: '#c7d5e4',
                                    borderRadius: 5,
                                    stack: 'salles',
                                },
                            ],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                x: { stacked: true, grid: { display: false } },
                                y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } },
                            },
                            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } },
                        },
                    };
                };

                let graphique = null;

                const dessiner = function () {
                    if (!canvas || typeof Chart === 'undefined') {
                        return;
                    }
                    if (!graphique) {
                        graphique = new Chart(canvas, configuration());
                        return;
                    }
                    graphique.data.labels = etages.map(e => 'Étage ' + e.etage);
                    graphique.data.datasets[0].data = etages.map(e => e.libres);
                    graphique.data.datasets[1].data = etages.map(e => e.total - e.libres);
                    graphique.update();
                };

                dessiner();

                // Chart.js ne recalcule pas toujours son canvas quand la colonne change
                // de largeur : le graphique restait à son ancienne taille et débordait de
                // la carte. On le redessine alors à la bonne largeur.
                const conteneurGraphique = canvas ? canvas.parentElement : null;
                let largeurConnue = conteneurGraphique ? conteneurGraphique.clientWidth : 0;

                const ajuster = function () {
                    if (!conteneurGraphique || !graphique) {
                        return;
                    }
                    const largeur = conteneurGraphique.clientWidth;
                    if (!largeur || Math.abs(largeur - largeurConnue) < 2) {
                        return;
                    }
                    largeurConnue = largeur;
                    graphique.destroy();
                    graphique = new Chart(canvas, configuration());
                };

                if (conteneurGraphique && typeof ResizeObserver !== 'undefined') {
                    new ResizeObserver(ajuster).observe(conteneurGraphique);
                }
                window.addEventListener('resize', ajuster);

                // ── Libellés ─────────────────────────────────────────────────
                const afficherCreneau = function () {
                    const libres = etages.reduce((somme, e) => somme + e.libres, 0);
                    const total = etages.reduce((somme, e) => somme + e.total, 0);

                    resume.textContent = libres + ' salle' + (libres > 1 ? 's' : '') + ' libre' + (libres > 1 ? 's' : '')
                        + ' sur ' + total;
                    libelleCreneau.textContent = 'Créneau de 1 h · ' + creneau.debutLisible + ' → ' + creneau.finLisible;
                };

                // ── Recalcul sur le créneau choisi ───────────────────────────
                let enCours = null;

                const charger = function () {
                    const parametres = new URLSearchParams({ creneau: selecteur.value });
                    if (interactif) {
                        parametres.set('interactif', '1');
                    }

                    if (enCours) {
                        enCours.abort();
                    }
                    enCours = new AbortController();

                    fetch(urlOccupation + '?' + parametres.toString(), {
                        headers: { Accept: 'application/json' },
                        signal: enCours.signal,
                    })
                        .then(reponse => reponse.ok ? reponse.json() : null)
                        .then(donnees => {
                            if (!donnees) {
                                return;
                            }
                            etages = donnees.planEtages;
                            creneau = donnees;
                            selecteur.value = donnees.debutLocal;
                            conteneurPlan.innerHTML = donnees.plan;
                            afficherCreneau();
                            dessiner();
                        })
                        .catch(erreur => {
                            if (erreur.name !== 'AbortError') {
                                console.error(erreur);
                            }
                        });
                };

                selecteur.addEventListener('change', charger);

                // ── Pastille cliquable → pré-remplissage de la demande ───────
                conteneurPlan.addEventListener('click', function (evenement) {
                    const pastille = evenement.target.closest('button.room-chip');
                    if (!pastille || pastille.disabled) {
                        return;
                    }

                    const salle = document.getElementById('salle_id');
                    const debut = document.getElementById('date_debut');
                    const fin = document.getElementById('date_fin');
                    if (!salle || !debut || !fin) {
                        return;
                    }

                    salle.value = pastille.dataset.salle;
                    debut.value = creneau.debutLocal;
                    fin.value = creneau.finLocal;

                    salle.focus({ preventScroll: true });
                    salle.scrollIntoView({ behavior: 'smooth', block: 'center' });
                });
            })();
        </script>
    @endpush
@endonce
