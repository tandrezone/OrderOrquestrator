<?php

declare(strict_types=1);

namespace Tandrezone\OrderOrchestrator\Tests;

use PHPUnit\Framework\TestCase;
use Tandrezone\OrderOrchestrator\OrderOrchestrator;
use Tandrezone\Ztemp\TemplateEngine;

final class OrderOrchestratorTest extends TestCase
{
    public function testCalculateSubtotalSumsAndRoundsPrices(): void
    {
        $orchestrator = new OrderOrchestrator(shippingFile: $this->createShippingFile());

        $subtotal = $orchestrator->calculateSubtotal([
            ['name' => 'A', 'price' => '10.105'],
            ['name' => 'B', 'price' => 5],
            ['name' => 'C'],
        ]);

        self::assertSame(15.11, $subtotal);
    }

    public function testLoadShippingOptionsNormalizesValues(): void
    {
        $shippingFile = $this->createShippingFile([
            ['code' => 'standard', 'label' => 'Standard', 'price' => 5],
            ['code' => 'express', 'label' => 'Express', 'price' => '12.5'],
            ['invalid' => true],
        ]);

        $orchestrator = new OrderOrchestrator(shippingFile: $shippingFile);

        $options = $orchestrator->loadShippingOptions();

        self::assertCount(2, $options);
        self::assertSame('standard', $options[0]['code']);
        self::assertSame('5.00', $options[0]['price_formatted']);
        self::assertSame(12.5, $options[1]['price']);
        self::assertSame('12.50', $options[1]['price_formatted']);
    }

    public function testCalculateTotalAddsMatchingShippingPrice(): void
    {
        $shippingFile = $this->createShippingFile([
            ['code' => 'standard', 'label' => 'Standard', 'price' => 7.25],
        ]);

        $orchestrator = new OrderOrchestrator(shippingFile: $shippingFile);

        $total = $orchestrator->calculateTotal([
            ['name' => 'Item 1', 'price' => 10],
            ['name' => 'Item 2', 'price' => 3.49],
        ], 'standard');

        self::assertSame(20.74, $total);
    }

    public function testRenderOrderFormPassesComputedDataToTemplateEngine(): void
    {
        $shippingFile = $this->createShippingFile([
            ['code' => 'standard', 'label' => 'Standard', 'price' => 5],
            ['code' => 'express', 'label' => 'Express', 'price' => 12.5],
        ]);

        $templateEngine = $this->createMock(TemplateEngine::class);
        $templateEngine->expects(self::once())
            ->method('render')
            ->with(
                'order-form.html',
                self::callback(static function (array $payload): bool {
                    self::assertSame('25.10', $payload['subtotal']);
                    self::assertSame('37.60', $payload['total']);
                    self::assertSame('selected', $payload['shippingOptions'][1]['selected']);
                    self::assertSame('22.10', $payload['products'][1]['price_formatted']);

                    return true;
                })
            )
            ->willReturn('<html>rendered</html>');

        $orchestrator = new OrderOrchestrator($templateEngine, $shippingFile);

        $html = $orchestrator->renderOrderForm([
            ['name' => 'Mouse', 'price' => 3],
            ['name' => 'Keyboard', 'price' => 22.1],
        ], 'express');

        self::assertSame('<html>rendered</html>', $html);
    }

    public function testLoadShippingOptionsThrowsForMissingFile(): void
    {
        $orchestrator = new OrderOrchestrator(shippingFile: '/tmp/not-existing-shipping-file.json');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Shipping file not found');

        $orchestrator->loadShippingOptions();
    }

    /** @param array<int, array<string, mixed>>|null $data */
    private function createShippingFile(?array $data = null): string
    {
        $file = tempnam(sys_get_temp_dir(), 'shipping-');
        if ($file === false) {
            throw new \RuntimeException('Unable to create temporary shipping file.');
        }

        $payload = $data ?? [
            ['code' => 'standard', 'label' => 'Standard', 'price' => 5.00],
        ];

        file_put_contents($file, (string) json_encode($payload, JSON_THROW_ON_ERROR));

        return $file;
    }
}
