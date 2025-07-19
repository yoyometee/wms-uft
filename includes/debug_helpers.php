<?php
// Debug helpers for DataTables troubleshooting

function createSampleData() {
    return [
        'recent_transactions' => [
            [
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                'ประเภทหลัก' => 'รับสินค้า',
                'sku' => 'ATG001',
                'product_name' => 'อาหารสุนัขรสเนื้อ 1กก.',
                'pallet_id' => 'ATG25000001',
                'location_id' => 'A-01-01-01',
                'ชิ้น' => 50,
                'น้ำหนัก' => 50.0,
                'name_edit' => 'ผู้ดูแลระบบ'
            ],
            [
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'ประเภทหลัก' => 'จัดเตรียมสินค้า',
                'sku' => 'ATG002',
                'product_name' => 'อาหารสุนัขรสไก่ 1กก.',
                'pallet_id' => 'ATG25000002',
                'location_id' => 'A-01-01-02',
                'ชิ้น' => 30,
                'น้ำหนัก' => 30.0,
                'name_edit' => 'พนักงานคลัง 1'
            ],
            [
                'created_at' => date('Y-m-d H:i:s', strtotime('-3 hours')),
                'ประเภทหลัก' => 'ย้ายสินค้า',
                'sku' => 'ATG003',
                'product_name' => 'อาหารแมวรสทูน่า 500กรัม',
                'pallet_id' => 'ATG25000003',
                'location_id' => 'PF-Zone-01',
                'ชิ้น' => 100,
                'น้ำหนัก' => 50.0,
                'name_edit' => 'เจ้าหน้าที่สำนักงาน'
            ]
        ],
        'location_utilization' => [
            [
                'zone' => 'Selective Rack',
                'total_locations' => 100,
                'occupied_locations' => 75,
                'available_locations' => 25,
                'utilization_percent' => 75.0
            ],
            [
                'zone' => 'PF-Zone',
                'total_locations' => 50,
                'occupied_locations' => 30,
                'available_locations' => 20,
                'utilization_percent' => 60.0
            ],
            [
                'zone' => 'PF-Premium',
                'total_locations' => 25,
                'occupied_locations' => 20,
                'available_locations' => 5,
                'utilization_percent' => 80.0
            ]
        ],
        'low_stock_products' => [
            [
                'sku' => 'ATG001',
                'product_name' => 'อาหารสุนัขรสเนื้อ 1กก.',
                'จำนวนถุง_ปกติ' => 80,
                'min_stock' => 100,
                'updated_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
            ],
            [
                'sku' => 'ATG004',
                'product_name' => 'ขนมสุนัขรสเนื้อ 200กรัม',
                'จำนวนถุง_ปกติ' => 450,
                'min_stock' => 500,
                'updated_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
            ]
        ],
        'expiring_soon' => [
            [
                'location_id' => 'A-01-01-01',
                'sku' => 'ATG001',
                'product_name' => 'อาหารสุนัขรสเนื้อ 1กก.',
                'pallet_id' => 'ATG25000001',
                'ชิ้น' => 50,
                'น้ำหนัก' => 50.0,
                'expiration_date' => strtotime('+5 days')
            ],
            [
                'location_id' => 'PF-Zone-01',
                'sku' => 'ATG003',
                'product_name' => 'อาหารแมวรสทูน่า 500กรัม',
                'pallet_id' => 'ATG25000003',
                'ชิ้น' => 100,
                'น้ำหนัก' => 50.0,
                'expiration_date' => strtotime('+2 days')
            ]
        ]
    ];
}

function validateDataTableStructure($table_name, $data) {
    $expected_structures = [
        'recent_transactions' => [
            'created_at', 'ประเภทหลัก', 'sku', 'product_name', 
            'pallet_id', 'location_id', 'ชิ้น', 'น้ำหนัก', 'name_edit'
        ],
        'location_utilization' => [
            'zone', 'total_locations', 'occupied_locations', 
            'available_locations', 'utilization_percent'
        ],
        'low_stock_products' => [
            'sku', 'product_name', 'จำนวนถุง_ปกติ', 'min_stock'
        ],
        'expiring_soon' => [
            'location_id', 'sku', 'product_name', 'pallet_id', 
            'ชิ้น', 'น้ำหนัก', 'expiration_date'
        ]
    ];
    
    if (!isset($expected_structures[$table_name])) {
        return ['valid' => false, 'error' => "Unknown table: $table_name"];
    }
    
    $expected_columns = $expected_structures[$table_name];
    $issues = [];
    
    if (empty($data)) {
        return ['valid' => true, 'warning' => "No data for table: $table_name"];
    }
    
    $first_row = $data[0];
    $actual_columns = array_keys($first_row);
    
    // Check for missing columns
    $missing_columns = array_diff($expected_columns, $actual_columns);
    if (!empty($missing_columns)) {
        $issues[] = "Missing columns: " . implode(', ', $missing_columns);
    }
    
    // Check for extra columns (not necessarily an error)
    $extra_columns = array_diff($actual_columns, $expected_columns);
    if (!empty($extra_columns)) {
        $issues[] = "Extra columns: " . implode(', ', $extra_columns);
    }
    
    return [
        'valid' => empty($missing_columns),
        'issues' => $issues,
        'expected_columns' => count($expected_columns),
        'actual_columns' => count($actual_columns),
        'data_rows' => count($data)
    ];
}

function logDataTableDebug($table_name, $data) {
    $validation = validateDataTableStructure($table_name, $data);
    
    $log_entry = date('Y-m-d H:i:s') . " - DataTable Debug: $table_name\n";
    $log_entry .= "Valid: " . ($validation['valid'] ? 'YES' : 'NO') . "\n";
    $log_entry .= "Data Rows: " . $validation['data_rows'] . "\n";
    
    if (isset($validation['issues']) && !empty($validation['issues'])) {
        $log_entry .= "Issues: " . implode('; ', $validation['issues']) . "\n";
    }
    
    if (isset($validation['warning'])) {
        $log_entry .= "Warning: " . $validation['warning'] . "\n";
    }
    
    if (!empty($data)) {
        $log_entry .= "Sample Row: " . json_encode($data[0], JSON_UNESCAPED_UNICODE) . "\n";
    }
    
    $log_entry .= "----------------------------------------\n";
    
    error_log($log_entry);
    
    return $validation;
}

function ensureDataStructure($table_name, $data) {
    $sample_data = createSampleData();
    
    // If no data, return sample data
    if (empty($data) && isset($sample_data[$table_name])) {
        error_log("Using sample data for $table_name - no real data found");
        return $sample_data[$table_name];
    }
    
    // Validate structure
    $validation = validateDataTableStructure($table_name, $data);
    
    if (!$validation['valid']) {
        error_log("Data structure invalid for $table_name: " . implode('; ', $validation['issues']));
        
        // Return sample data as fallback
        if (isset($sample_data[$table_name])) {
            error_log("Using sample data for $table_name as fallback");
            return $sample_data[$table_name];
        }
    }
    
    return $data;
}
?>