<?php use App\Core\Helpers; ?>
<main class="container py-5">
    <div class="glass-card p-5 text-center">
        <h1 class="display-6">Page not found</h1>
        <p class="text-secondary mb-0">The requested page does not exist.</p>
        <a href="<?= Helpers::escape(Helpers::url('/')) ?>" class="btn btn-primary mt-4">Return Home</a>
    </div>
</main>
