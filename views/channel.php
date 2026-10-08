<?php
use App\Core\Helpers;
?>
<main class="channel-screen container-fluid py-3 py-md-4">
    <div class="row g-3">
        <div class="col-12">
            <div class="glass-card p-3 p-md-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="logo-badge logo-badge-sm"><i class="fa-solid fa-tower-broadcast"></i></div>
                    <div>
                        <div class="small text-uppercase text-secondary">Walkie Talkie</div>
                        <div class="h5 mb-0"><?= Helpers::escape($appName ?? 'Walkie Talkie') ?></div>
                        <div class="small text-accent fw-semibold"><?= Helpers::escape('#' . ($session['channel'] ?? 'channel')) ?></div>
                    </div>
                </div>
                <div class="status-pill" id="connectionStatus" data-state="connecting">
                    <span class="status-dot"></span>
                    <span class="status-text">CONNECTING</span>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-5">
            <div class="glass-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <div class="small text-secondary">Current Speaker</div>
                        <div class="h4 mb-0" id="speakerLabel">Waiting for a speaker</div>
                    </div>
                    <div class="badge rounded-pill text-bg-dark" id="userCountBadge">0 USERS</div>
                </div>

                <div class="meter-card">
                    <canvas id="audioMeter" width="520" height="140" aria-label="Audio level meter"></canvas>
                </div>

                <div class="d-grid mt-4">
                    <button id="pttButton" class="ptt-button" type="button" aria-pressed="false">
                        <span class="ptt-label">HOLD TO TALK</span>
                        <span class="ptt-subtitle">Spacebar / touch / mouse</span>
                    </button>
                </div>

                <div class="mt-3 small text-secondary" id="microphoneStatus">Microphone is off.</div>
                <div class="mt-2 small text-warning" id="statusMessage" aria-live="polite"></div>

                <div class="mt-4 d-flex gap-2 flex-wrap">
                    <form method="post" action="<?= Helpers::escape(Helpers::url('/leave')) ?>" class="m-0">
                        <input type="hidden" name="_csrf" value="<?= Helpers::escape($csrfToken) ?>">
                        <button class="btn btn-outline-light" type="submit">
                            <i class="fa-solid fa-right-from-bracket me-2"></i>Leave Channel
                        </button>
                    </form>
                    <button class="btn btn-outline-info" id="copyChannelBtn" type="button">
                        <i class="fa-regular fa-copy me-2"></i>Copy Channel
                    </button>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-7">
            <div class="glass-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">On This Channel</h2>
                    <span class="small text-secondary">Max <?= (int) (($state['max_peers'] ?? 8)) ?> users</span>
                </div>
                <div id="participantList" class="participant-list"></div>
            </div>
        </div>
    </div>
</main>
