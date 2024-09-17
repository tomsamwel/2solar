<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use App\Mail\LowStockAlert;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Run the migrations and seeders
        $this->artisan('migrate:refresh', ['--seed' => true]);
    }

    /**
     * Test that an order is stored correctly in the database.
     */
    public function test_order_is_stored_correctly()
    {
        $payload = [
            'items' => [
                [
                    'system_id' => 1,
                    'quantity' => 2,
                ],
            ],
        ];

        $response = $this->postJson('/api/orders', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Order placed successfully',
            ]);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);

        $order = Order::first();
        $this->assertEquals(1, $order->orderItems()->count());

        $orderItem = OrderItem::first();
        $this->assertEquals(1, $orderItem->system_id);
        $this->assertEquals(2, $orderItem->quantity);
    }

    /**
     * Test that the stock levels are updated correctly after an order.
     */
    public function test_stock_is_updated_correctly()
    {
        $solarPanel = Product::where('name', 'Solar panel')->first();
        $inverter = Product::where('name', 'Inverter')->first();
        $optimizer = Product::where('name', 'Optimizer')->first();

        $initialSolarPanelStock = $solarPanel->stock;
        $initialInverterStock = $inverter->stock;
        $initialOptimizerStock = $optimizer->stock;

        $payload = [
            'items' => [
                [
                    'system_id' => 1,
                    'quantity' => 2,
                ],
            ],
        ];

        $response = $this->postJson('/api/orders', $payload);

        $response->assertStatus(201);

        $solarPanel->refresh();
        $inverter->refresh();
        $optimizer->refresh();

        $this->assertEquals($initialSolarPanelStock - (12 * 2), $solarPanel->stock);
        $this->assertEquals($initialInverterStock - (1 * 2), $inverter->stock);
        $this->assertEquals($initialOptimizerStock - (12 * 2), $optimizer->stock);
    }

    /**
     * Test that an email is sent when stock drops below 20% of initial stock.
     */
    public function test_email_is_sent_when_stock_below_20_percent()
    {
        // Set up initial stock levels to trigger the low stock alert
        $solarPanel = Product::where('name', 'Solar panel')->first();
        $solarPanel->initial_stock = 1000;
        $solarPanel->stock = 210; // 20% of 1000 is 200
        $solarPanel->low_stock_notified = false;
        $solarPanel->save();

        Mail::fake();

        $payload = [
            'items' => [
                [
                    'system_id' => 1,
                    'quantity' => 1, // This will consume 12 solar panels
                ],
            ],
        ];

        $response = $this->postJson('/api/orders', $payload);

        $response->assertStatus(201);

        Mail::assertSent(LowStockAlert::class, function ($mail) use ($solarPanel) {
            return $mail->product->id === $solarPanel->id;
        });

        // Ensure low_stock_notified is set to true
        $solarPanel->refresh();

        # Switch to loose boolean comparison for sqllite compatibility..
        // $this->assertTrue($solarPanel->low_stock_notified);
        $this->assertEquals(1, $solarPanel->low_stock_notified);
    }

    /**
     * Test that no duplicate emails are sent when stock is already below 20%.
     */
    public function test_no_duplicate_emails_sent_when_stock_already_below_20_percent()
    {
        // Set low_stock_notified to true to simulate that an email has already been sent
        $solarPanel = Product::where('name', 'Solar panel')->first();
        $solarPanel->initial_stock = 1000;
        $solarPanel->stock = 180; // Below 20% of initial stock
        $solarPanel->low_stock_notified = true;
        $solarPanel->save();

        Mail::fake();

        $payload = [
            'items' => [
                [
                    'system_id' => 1,
                    'quantity' => 1, // Further decreases stock
                ],
            ],
        ];

        $response = $this->postJson('/api/orders', $payload);

        $response->assertStatus(201);

        Mail::assertNotSent(LowStockAlert::class);
    }

    /**
     * Test that the application handles insufficient stock appropriately.
     */
    public function test_order_fails_when_insufficient_stock()
    {
        // Set stock levels to be insufficient for the order
        $inverter = Product::where('name', 'Inverter')->first();
        $inverter->stock = 0;
        $inverter->save();

        $payload = [
            'items' => [
                [
                    'system_id' => 1,
                    'quantity' => 1,
                ],
            ],
        ];

        $response = $this->postJson('/api/orders', $payload);

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Insufficient stock for product Inverter',
            ]);

        // Ensure no order or order items were created
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    /**
     * Test that the low_stock_notified flag is reset when stock is replenished above 20%.
     */
    public function test_low_stock_notified_flag_is_reset_when_stock_replenished()
    {
        // Simulate stock below 20% and notification sent
        $optimizer = Product::where('name', 'Optimizer')->first();
        $optimizer->initial_stock = 500;
        $optimizer->stock = 90; // Below 20% of initial stock (100 units)
        $optimizer->low_stock_notified = true;
        $optimizer->save();

        // Replenish stock above 20% using the replenishStock method
        $optimizer->replenishStock(310); // Increases stock from 90 to 400

        // Check that low_stock_notified flag is reset
        $optimizer->refresh();

        # Switch to loose boolean comparison for sqllite compatibility..
        // $this->assertFalse($optimizer->low_stock_notified);
        $this->assertEquals(0, $optimizer->low_stock_notified);
    }

    public function test_email_is_sent_again_after_stock_replenished_and_drops_below_20_percent()
    {
        Mail::fake();

        // Simulate stock below 20% and notification sent
        $optimizer = Product::where('name', 'Optimizer')->first();
        $optimizer->initial_stock = 500;
        $optimizer->stock = 90; // Below 20%
        $optimizer->low_stock_notified = true;
        $optimizer->save();

        // Replenish stock above 20% using the replenishStock method
        $optimizer->replenishStock(60); // Increases stock from 90 to 150

        // Place an order to drop stock below 20% again
        $payload = [
            'items' => [
                [
                    'system_id' => 1,
                    'quantity' => 5, // Consumes 12 * 5 = 60 optimizers
                ],
            ],
        ];

        $response = $this->postJson('/api/orders', $payload);

        $response->assertStatus(201);

        // Assert that an email is sent again after stock drops below 20% again
        Mail::assertSent(LowStockAlert::class, function ($mail) use ($optimizer) {
            return $mail->product->id === $optimizer->id;
        });

        // Ensure low_stock_notified is set to true again
        $optimizer->refresh();

        # Switch to loose boolean comparison for sqllite compatibility..
        // $this->assertTrue($optimizer->low_stock_notified);
        $this->assertEquals(1, $optimizer->low_stock_notified);
    }
}
