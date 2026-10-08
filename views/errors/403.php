<?php use App\Core\Helpers; ?>
<main class="container py-5">
    <div class="glass-card p-5 text-center">
        <h1 class="display-6">Access denied</h1>
        <p class="text-secondary mb-0">The request could not be verified.</p>
        <a href="<?= Helpers::escape(Helpers::url('/')) ?>" class="btn btn-primary mt-4">Return to Join Page</a>
    </div>
</main>
