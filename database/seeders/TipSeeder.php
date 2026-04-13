<?php

namespace Database\Seeders;

use App\Models\Tip;
use Illuminate\Database\Seeder;

class TipSeeder extends Seeder
{
    public function run(): void
    {
        $tips = [
            ['prediction_type' => '1X2', 'label' => 'Home Win', 'value' => '1', 'sort_order' => 1],
            ['prediction_type' => '1X2', 'label' => 'Draw', 'value' => 'X', 'sort_order' => 2],
            ['prediction_type' => '1X2', 'label' => 'Away Win', 'value' => '2', 'sort_order' => 3],

            ['prediction_type' => 'Double Chance', 'label' => 'Home or Draw', 'value' => '1X', 'sort_order' => 10],
            ['prediction_type' => 'Double Chance', 'label' => 'Away or Draw', 'value' => 'X2', 'sort_order' => 11],
            ['prediction_type' => 'Double Chance', 'label' => 'Home or Away', 'value' => '12', 'sort_order' => 12],

            ['prediction_type' => 'Both Teams To Score', 'label' => 'Yes', 'value' => 'Yes', 'sort_order' => 20],
            ['prediction_type' => 'Both Teams To Score', 'label' => 'No', 'value' => 'No', 'sort_order' => 21],

            ['prediction_type' => 'Under/Over 1.5', 'label' => 'Over 1.5', 'value' => 'Over 1.5', 'sort_order' => 30],
            ['prediction_type' => 'Under/Over 1.5', 'label' => 'Under 1.5', 'value' => 'Under 1.5', 'sort_order' => 31],
            ['prediction_type' => 'Under/Over 2.5', 'label' => 'Over 2.5', 'value' => 'Over 2.5', 'sort_order' => 32],
            ['prediction_type' => 'Under/Over 2.5', 'label' => 'Under 2.5', 'value' => 'Under 2.5', 'sort_order' => 33],
            ['prediction_type' => 'Under/Over 3.5', 'label' => 'Over 3.5', 'value' => 'Over 3.5', 'sort_order' => 34],
            ['prediction_type' => 'Under/Over 3.5', 'label' => 'Under 3.5', 'value' => 'Under 3.5', 'sort_order' => 35],

            ['prediction_type' => 'Draw No Bet', 'label' => 'Home DNB', 'value' => 'Home DNB', 'sort_order' => 40],
            ['prediction_type' => 'Draw No Bet', 'label' => 'Away DNB', 'value' => 'Away DNB', 'sort_order' => 41],

            ['prediction_type' => 'Half Time', 'label' => 'Home HT Win', 'value' => 'HT 1', 'sort_order' => 50],
            ['prediction_type' => 'Half Time', 'label' => 'HT Draw', 'value' => 'HT X', 'sort_order' => 51],
            ['prediction_type' => 'Half Time', 'label' => 'Away HT Win', 'value' => 'HT 2', 'sort_order' => 52],

            ['prediction_type' => 'Half Time/Full Time', 'label' => '1/1', 'value' => '1/1', 'sort_order' => 60],
            ['prediction_type' => 'Half Time/Full Time', 'label' => '1/X', 'value' => '1/X', 'sort_order' => 61],
            ['prediction_type' => 'Half Time/Full Time', 'label' => '1/2', 'value' => '1/2', 'sort_order' => 62],
            ['prediction_type' => 'Half Time/Full Time', 'label' => 'X/1', 'value' => 'X/1', 'sort_order' => 63],
            ['prediction_type' => 'Half Time/Full Time', 'label' => 'X/X', 'value' => 'X/X', 'sort_order' => 64],
            ['prediction_type' => 'Half Time/Full Time', 'label' => 'X/2', 'value' => 'X/2', 'sort_order' => 65],
            ['prediction_type' => 'Half Time/Full Time', 'label' => '2/1', 'value' => '2/1', 'sort_order' => 66],
            ['prediction_type' => 'Half Time/Full Time', 'label' => '2/X', 'value' => '2/X', 'sort_order' => 67],
            ['prediction_type' => 'Half Time/Full Time', 'label' => '2/2', 'value' => '2/2', 'sort_order' => 68],

            ['prediction_type' => 'Correct Score', 'label' => '1-0', 'value' => '1-0', 'sort_order' => 70],
            ['prediction_type' => 'Correct Score', 'label' => '2-0', 'value' => '2-0', 'sort_order' => 71],
            ['prediction_type' => 'Correct Score', 'label' => '2-1', 'value' => '2-1', 'sort_order' => 72],
            ['prediction_type' => 'Correct Score', 'label' => '1-1', 'value' => '1-1', 'sort_order' => 73],
            ['prediction_type' => 'Correct Score', 'label' => '0-1', 'value' => '0-1', 'sort_order' => 74],
            ['prediction_type' => 'Correct Score', 'label' => '1-2', 'value' => '1-2', 'sort_order' => 75],
        ];

        foreach ($tips as $tip) {
            Tip::updateOrCreate(
                [
                    'prediction_type' => $tip['prediction_type'],
                    'value' => $tip['value'],
                ],
                [
                    ...$tip,
                    'is_active' => true,
                ]
            );
        }
    }
}
