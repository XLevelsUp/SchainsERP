<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\UserDetail;
use App\Models\StockInDetail;
use App\Models\GmsInHistory;
use App\Models\NumericWastageIn;
use App\Models\UsersItemsMapping;
use App\Models\WastageDetails;
use App\Models\CashTxn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StockInDetailsTest extends TestCase
{
    use RefreshDatabase;

    protected UserDetail $head;
    protected UserDetail $employee;
    protected Item $goldRing;
    protected Item $goldChain;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed basic users
        $this->head = UserDetail::create([
            'name' => 'Head Admin',
            'user_name' => 'head_admin',
            'password_hash' => 'dummy',
            'address' => 'Test Address',
            'signature' => 'HA',
            'code' => 'HA001',
            'phone_no' => '1234567890',
            'remarks' => 'Test Head User',
            'proff' => 'Head',
            'role_id' => 1,
            'mailing_name' => 'Head Admin Mailing',
            'category_name' => 'BOTH',
            'system_id' => 'SYS_HEAD',
            'is_customerfitem_cal_enabled' => false,
            'is_customerfitem_cal_in_enabled' => false,
            'is_active' => true,
            'is_delete' => false,
            'is_billable' => true,
            'grams_grand_total' => 1000.0000,
            'purity_grand_total' => 920.0000,
            'rak_cash_balance' => 50000.0000,
        ]);

        $this->employee = UserDetail::create([
            'name' => 'Employee One',
            'user_name' => 'employee_one',
            'password_hash' => 'dummy',
            'address' => 'Test Address',
            'signature' => 'E1',
            'code' => 'E001',
            'phone_no' => '0987654321',
            'remarks' => 'Test Employee User',
            'proff' => 'Employee',
            'role_id' => 2,
            'mailing_name' => 'Employee One Mailing',
            'category_name' => 'BOTH',
            'system_id' => 'SYS_EMP1',
            'is_customerfitem_cal_enabled' => false,
            'is_customerfitem_cal_in_enabled' => false,
            'is_active' => true,
            'is_delete' => false,
            'is_billable' => true,
            'grams_grand_total' => 100.0000,
            'purity_grand_total' => 92.0000,
            'rak_cash_balance' => 1000.0000,
        ]);

        // Seed items
        $this->goldRing = Item::create([
            'item_name' => 'Gold Ring',
            'is_active' => true,
            'default_touch' => 92.00,
            'item_touch' => 90.00,
            'is_need_fitem_shown' => false,
            'is_barcode' => true,
            'is_no_barcode' => false,
        ]);

        $this->goldChain = Item::create([
            'item_name' => 'Gold Chain',
            'is_active' => true,
            'default_touch' => 92.00,
            'item_touch' => 90.00,
            'is_need_fitem_shown' => false,
            'is_barcode' => true,
            'is_no_barcode' => false,
        ]);

        // Initialize user item mappings
        UsersItemsMapping::create([
            'user_id' => $this->head->user_id,
            'item_id' => $this->goldRing->item_id,
            'item_grams_total' => 1000.0000,
            'item_purity_total' => 920.0000,
            'is_primary' => 1,
        ]);

        UsersItemsMapping::create([
            'user_id' => $this->employee->user_id,
            'item_id' => $this->goldRing->item_id,
            'item_grams_total' => 100.0000,
            'item_purity_total' => 92.0000,
            'is_primary' => 1,
        ]);

        // Seed core wastage details for validation
        WastageDetails::create([
            'waste_id' => 1,
            'waste_name' => 'RUVIE DYE',
            'waste_value' => 0.1800,
            'added_by' => 1,
        ]);
    }

    public function test_stock_in_success(): void
    {
        $payload = [
            'given_by' => $this->employee->user_id,
            'given_to' => $this->head->user_id,
            'items' => [
                [
                    'item_id' => $this->goldRing->item_id,
                    'grams' => 50.0000,
                    'touch' => 92.0000,
                    'waste_id' => null,
                    'waste_total' => 0.0000,
                    'remarks' => 'Returning ring sample',
                    'item_remarks' => 'Clean return',
                ]
            ]
        ];

        $response = $this->withHeaders([
            'X-User-ID' => $this->head->user_id,
        ])->postJson('/api/v1/stock/in', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);

        // Verify balance adjustments in database
        $this->head->refresh();
        $this->employee->refresh();

        // Worker drops from 100 to 50 grams
        $this->assertEquals(50.0000, (float)$this->employee->grams_grand_total);
        // Head increases from 1000 to 1050 grams
        $this->assertEquals(1050.0000, (float)$this->head->grams_grand_total);

        // Check StockInDetail row
        $this->assertDatabaseHas('stock_in_details', [
            'item_id' => $this->goldRing->item_id,
            'given_by' => $this->employee->user_id,
            'given_to' => $this->head->user_id,
            'grams' => 50.0000,
            'purity' => 46.0000,
            'stock_type' => 'IN',
        ]);
    }

    public function test_gms_in_success(): void
    {
        $payload = [
            'given_by' => $this->employee->user_id,
            'given_to' => $this->head->user_id,
            'items' => [
                [
                    'item_id' => $this->goldRing->item_id,
                    'grams' => 100.0000,
                    'stone' => 5.0000,
                    'thread' => 1.0000,
                    'wastage' => 4.0000,
                    'hall_mark' => 91.6000,
                    'mtouch' => 20.0000,
                    'mtouch_wastage' => 0.0500,
                    'remarks' => 'GMS Return 1',
                    'item_remarks' => 'Worker return'
                ]
            ]
        ];

        $response = $this->withHeaders([
            'X-User-ID' => $this->head->user_id,
        ])->postJson('/api/v1/stock/gms-in', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);

        $this->head->refresh();
        $this->employee->refresh();

        // GMS In calculation:
        // netGrams = 100 - 6 = 94
        // wastageGrams = 94 * 4% = 3.76
        // purity = (94 + 3.76) * 91.6% = 97.76 * 91.6 / 100 = 89.5481 (BCMath scale 4 truncation)
        // Worker drops by 100 grams
        $this->assertEquals(0.0000, (float)$this->employee->grams_grand_total);
        $this->assertEquals(2.4519, (float)$this->employee->purity_grand_total); // 92 - 89.5481

        // Head gains 100 grams
        $this->assertEquals(1100.0000, (float)$this->head->grams_grand_total);
        $this->assertEquals(1009.5481, (float)$this->head->purity_grand_total); // 920 + 89.5481

        $this->assertDatabaseHas('gms_in_histories', [
            'item_id' => $this->goldRing->item_id,
            'grams' => 100.0000,
            'stone' => 5.0000,
            'thread' => 1.0000,
            'wastage' => 4.0000,
            'hall_mark' => 91.6000,
            'total' => 89.5481,
            'gms_type' => 'IN',
        ]);
    }

    public function test_numeric_wastage_in_success_with_cash(): void
    {
        $payload = [
            'given_by' => $this->employee->user_id,
            'given_to' => $this->head->user_id,
            'items' => [
                [
                    'item_id' => $this->goldRing->item_id,
                    'grams' => 10.0000,
                    'touch' => 92.0000,
                    'no_of_pcs' => 10.0000,
                    'amount_pcs' => 100.0000,
                    'waste_id' => 1,
                    'waste_total' => 0.1000,
                ]
            ]
        ];

        $response = $this->withHeaders([
            'X-User-ID' => $this->head->user_id,
        ])->postJson('/api/v1/stock/numeric-waste-in', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);

        $this->head->refresh();
        $this->employee->refresh();

        // Payout: 10 * 100 = 1000.0
        // Head rak_cash_balance increases: 50000 + 1000 = 51000.0
        // Employee rak_cash_balance decreases: 1000 - 1000 = 0.0
        $this->assertEquals(51000.0000, (float)$this->head->rak_cash_balance);
        $this->assertEquals(0.0000, (float)$this->employee->rak_cash_balance);

        $this->assertDatabaseHas('numeric_wastage_in_records', [
            'item_id' => $this->goldRing->item_id,
            'grams' => 10.0000,
            'touch' => 92.0000,
            'no_of_pcs' => 10.0000,
            'amount' => 1000.0000,
            'type' => 'IN',
        ]);
    }
}
