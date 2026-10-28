<div class="branding-fields">
    <?php foreach (['logo'=>'School logo', 'favicon'=>'Browser icon (favicon)'] as $kind=>$label): $path=school()[$kind.'_path']??null; ?>
    <div class="branding-field">
        <div class="branding-preview <?= $kind==='favicon'?'favicon-preview':'' ?>" aria-label="<?= e($label) ?> preview">
            <img src="<?= e($path ? url($path) : ($kind==='favicon' ? school_favicon() : asset('favicon.svg'))) ?>" alt="Current <?= e(strtolower($label)) ?>" data-branding-preview="<?= $kind ?>">
        </div>
        <div class="branding-control">
            <label><?= e($label) ?><input type="file" name="<?= $kind ?>" accept="image/png,image/jpeg,image/webp" data-branding-upload="<?= $kind ?>" aria-describedby="<?= $kind ?>-help"></label>
            <small id="<?= $kind ?>-help">PNG, JPG, or WebP · Up to 2 MB. <?= $kind==='logo'?'A square image with a transparent background works best.':'A simple square image works best; saved as a 64 × 64 PNG.' ?></small>
            <?php if ($path): ?><label class="branding-remove"><input type="checkbox" name="remove_<?= $kind ?>" value="1"> Remove current <?= $kind ?> and use the default</label><?php endif ?>
        </div>
    </div>
    <?php endforeach ?>
    <small>Leave a file empty to keep its current image. A new upload takes priority over removal.</small>
</div>
