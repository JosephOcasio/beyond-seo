<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($title ?? ''); ?></title>
    <link rel="stylesheet" href="<?php echo esc_url($stylePath ?? ''); ?>">
    <style><?php echo esc_html( wp_strip_all_tags($styles ?? '') ); ?></style>
</head>
<body>
<div class="rc-error-container">
    <header class="rc-error-header">
        <h1><?php echo esc_html($title ?? ''); ?></h1>
    </header>
    <section class="rc-error-body">
        <p><?php echo esc_html($description ?? ''); ?></p>
		<?php if (!empty($reasons)): ?>
            <ul>
				<?php foreach ($reasons as $reason): ?>
                <li><?php echo esc_html($reason); ?></li>
				<?php endforeach; ?>
            </ul>
		<?php endif; ?>
    </section>
	<?php if (!empty($showFooter)): ?>
        <footer class="rc-error-footer">
            <p><?php echo wp_kses_post($footer ?? ''); ?></p>
        </footer>
	<?php endif; ?>
</div>
</body>
</html>
