<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Mail;
use App\Mail\LowStockAlert;

class Product extends Model
{
    protected $fillable = ['name', 'initial_stock', 'stock', 'low_stock_notified'];

    /**
     * The systems that the product belongs to.
     */
    public function systems(): BelongsToMany
    {
        return $this->belongsToMany(System::class)
            ->withPivot('quantity')
            ->withTimestamps();
    }

    /**
     * Reduce the stock of the product by the given quantity.
     *
     * @param int $quantity
     * @throws \Exception
     */
    public function reduceStock(int $quantity)
    {
        // Check if there's enough stock
        if ($this->stock < $quantity) {
            throw new \Exception("Insufficient stock for product {$this->name}");
        }

        // Remember the previous stock to determine if notification is needed
        $previousStock = $this->stock;

        // Update the stock
        $this->stock -= $quantity;

        // Calculate the 20% threshold based on initial stock
        $threshold = $this->initial_stock * 0.2;

        // Reset low_stock_notified if stock replenished above threshold
        if ($this->stock > $threshold && $this->low_stock_notified) {
            $this->low_stock_notified = false;
        }

        // Check if stock drops below or equals 20% of initial stock
        if ($previousStock > $threshold && $this->stock <= $threshold && !$this->low_stock_notified) {
            // Send email notification
            Mail::to('wholesaler@example.com')->send(new LowStockAlert($this));
            // #add mail job to queue

            // Set low_stock_notified to true
            $this->low_stock_notified = true;
        }

        // Save the product
        $this->save();
    }

    /**
     * Replenish the stock of the product by the given quantity.
     *
     * @param int $quantity
     */
    public function replenishStock(int $quantity)
    {
        // Remember the previous stock to determine if notification flag should be reset
        $previousStock = $this->stock;

        // Update the stock
        $this->stock += $quantity;

        // Calculate the 20% threshold based on initial stock
        $threshold = $this->initial_stock * 0.2;

        // Reset low_stock_notified if stock replenished above threshold
        if ($previousStock <= $threshold && $this->stock > $threshold && $this->low_stock_notified) {
            $this->low_stock_notified = false;
        }

        // Save the product
        $this->save();
    }
}
