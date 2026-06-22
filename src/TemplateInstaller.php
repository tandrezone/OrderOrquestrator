<?php

declare(strict_types=1);

namespace Tandrezone\OrderOrchestrator;

final class TemplateInstaller
{
    public static function install(string $projectRoot): void
    {
        self::copyTemplate(
            $projectRoot,
            '/resources/templates/order-form.html',
            '/templates/order-form.html',
            'Order form'
        );
        self::copyTemplate(
            $projectRoot,
            '/resources/templates/admin/orders.html',
            '/templates/admin/orders.html',
            'Admin orders'
        );
    }

    private static function copyTemplate(
        string $projectRoot,
        string $sourceSuffix,
        string $targetSuffix,
        string $label
    ): void {
        $source = $projectRoot . $sourceSuffix;
        $target = $projectRoot . $targetSuffix;
        $targetDirectory = dirname($target);

        if (!is_file($source)) {
            fwrite(STDERR, "{$label} template source not found: {$source}\n");
            return;
        }

        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0755, true);
        }

        if (!copy($source, $target)) {
            fwrite(STDERR, "Unable to copy {$label} template to: {$target}\n");
            return;
        }

        fwrite(STDOUT, "{$label} template copied to: {$target}\n");
    }
}
