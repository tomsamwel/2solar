<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\System;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        // Minimal validation to ensure required keys are present
        $request->validate([
            'items' => 'required|array',
            'items.*.system_id' => 'required|exists:systems,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();

        try {
            // Create a new order
            $order = Order::create();

            foreach ($request->items as $item) {
                $system = System::findOrFail($item['system_id']);
                $quantity = $item['quantity'];

                // Create order item
                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'system_id' => $system->id,
                    'quantity' => $quantity,
                ]);

                // Update stock levels for each product in the system
                foreach ($system->products as $product) {
                    // Quantity required per system
                    $quantityPerSystem = $product->pivot->quantity;

                    // Total quantity needed for this order item
                    $totalQuantityNeeded = $quantityPerSystem * $quantity;

                    // Reduce product stock (handles email and flag updates)
                    $product->reduceStock($totalQuantityNeeded);
                }
            }

            DB::commit();

            return response()->json(['message' => 'Order placed successfully'], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            // Placeholder: Error handling and logging would go here
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}