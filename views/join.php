<?php
use App\Core\Helpers;
use App\Core\Session;
?>
<main class="auth-screen container py-5">
    <div class="row g-4 align-items-center justify-content-center">
        <div class="col-lg-7">
            <div class="brand-card glass-card p-4 p-md-5">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="logo-badge"><i class="fa-solid fa-tower-broadcast"></i></div>
                    <div>
                        <div class="text-uppercase text-accent small fw-semibold">PWA-Based Walkie Talkie</div>
                        <h1 class="display-6 mb-0"><?= Helpers::escape($appName ?? 'Walkie Talkie') ?></h1>
                    </div>
                </div>
                <p class="lead text-secondary mb-4">Push to talk, join a shared voice channel, and communicate in real time with a modern browser-based radio experience.</p>

                <?php if ($error = Session::get('join_error')): ?>
                    <div class="alert alert-warning border-0 bg-warning-subtle text-warning-emphasis">
                        <?= Helpers::escape((string) $error) ?>
                    </div>
                    <?php Session::remove('join_error'); ?>
                <?php endif; ?>

                <form method="post" action="<?= Helpers::escape(Helpers::url('/join')) ?>" class="join-form">
                    <input type="hidden" name="_csrf" value="<?= Helpers::escape($csrfToken) ?>">
                    <div class="mb-3">
                        <label for="nickname" class="form-label">Nickname</label>
                        <input type="text" class="form-control form-control-lg" id="nickname" name="nickname" maxlength="24" placeholder="Enter your nickname" required autocomplete="nickname">
                    </div>
                    <div class="mb-4">
                        <label for="channel" class="form-label">Channel</label>
                        <input type="text" class="form-control form-control-lg" id="channel" name="channel" maxlength="64" placeholder="Enter channel name" required autocomplete="off">
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100 fw-semibold">
                        <i class="fa-solid fa-right-to-bracket me-2"></i>Join Channel
                    </button>
                </form>

                <p class="small text-secondary mt-3 mb-0">Enter the same channel name as your team members to communicate together.</p>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="glass-card p-4 p-md-5">
                <h2 class="h4 mb-3">Open on your phone</h2>
                <p class="text-secondary mb-3">Scan this code with your phone camera to open the app.</p>
                <div class="qr-wrap mb-3">
                    <?php $qrUrl = $publicUrl ?? Helpers::absoluteUrl('/'); ?>
                    <div class="qr-code" data-qr="<?= Helpers::escape($qrUrl) ?>" aria-label="QR code for opening Walkie Talkie on a phone">
                        <img
                            class="qr-fallback"
                            src="https://api.qrserver.com/v1/create-qr-code/?size=196x196&amp;format=png&amp;data=<?= rawurlencode($qrUrl) ?>"
                            alt="Scan to open Walkie Talkie"
                            width="196"
                            height="196"
                        >
                    </div>
                </div>
                <div class="code-box small text-break"><?= Helpers::escape($qrUrl) ?></div>
                <hr class="border-secondary-subtle my-4">
                <div class="small text-secondary">
                    <div><i class="fa-solid fa-circle-info me-2"></i>Microphone access is requested only when you talk.</div>
                    <div class="mt-2"><i class="fa-solid fa-wifi me-2"></i>Best results on the same Wi-Fi network.</div>
                    <div class="mt-2"><i class="fa-solid fa-mobile-screen-button me-2"></i>For phone testing, replace <code>localhost</code> with this computer's LAN IP.</div>
                </div>
            </div>
        </div>
    </div>
</main>
