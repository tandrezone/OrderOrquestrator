<?php

declare(strict_types=1);

namespace Tandrezone\OrderOrchestrator;

final class TemplateInstaller
{
    public static function install(string $projectRoot): void
    {
        $sourceDir = $projectRoot . '/resources/templates';
        $targetDir = $projectRoot . '/templates';

        $templates = [
            'order-form.html',
            'order.html',
            'confirmation.html',
        ];

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        foreach ($templates as $template) {
            $source = $sourceDir . '/' . $template;
            $target = $targetDir . '/' . $template;

            if (!is_file($source)) {
                fwrite(STDERR, "Template source not found: {$source}\n");
                continue;
            }

            if (!copy($source, $target)) {
                fwrite(STDERR, "Unable to copy template to: {$target}\n");
                continue;
            }

            fwrite(STDOUT, "Template copied to: {$target}\n");
        }
    }
}
