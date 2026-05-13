<?php

declare(strict_types=1);

namespace Tandrezone\OrderOrquestrator;

final class TemplateInstaller
{
    public static function install(string $projectRoot): void
    {
        $source = $projectRoot . '/resources/templates/order-form.html';
        $targetDirectory = $projectRoot . '/templates';
        $target = $targetDirectory . '/order-form.html';

        if (!is_file($source)) {
            fwrite(STDERR, "Order form template source not found: {$source}\n");
            return;
        }

        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0777, true);
        }

        if (!copy($source, $target)) {
            fwrite(STDERR, "Unable to copy order form to: {$target}\n");
            return;
        }

        fwrite(STDOUT, "Order form copied to: {$target}\n");
    }
}
