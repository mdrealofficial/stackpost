<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\AdminAITemplateCategories\Models\AiTemplateCategory;

class AITemplateCategorySeeder extends Seeder
{
    public function run(): void
    {
        $changedAt = time();

        $categories = [
            ['id_secure' => '65a1059f85282', 'name' => 'Suggested', 'desc' => null, 'icon' => 'fa-light fa-stars', 'color' => 'dark', 'status' => true, 'created' => 0],
            ['id_secure' => '65a1059f85951', 'name' => 'Facebook', 'desc' => null, 'icon' => 'fa-brands fa-square-facebook', 'color' => 'warning', 'status' => true, 'created' => 0],
            ['id_secure' => '65a1059f85fda', 'name' => 'Instagram', 'desc' => null, 'icon' => 'fa-brands fa-instagram', 'color' => 'info', 'status' => true, 'created' => 0],
            ['id_secure' => '65a1059f86883', 'name' => 'X (Twitter)', 'desc' => null, 'icon' => 'fa-brands fa-x-twitter', 'color' => 'success', 'status' => true, 'created' => 0],
            ['id_secure' => '65a1059f87128', 'name' => 'LinkedIn', 'desc' => null, 'icon' => 'fa-brands fa-linkedin', 'color' => 'danger', 'status' => true, 'created' => 0],
            ['id_secure' => '65a1059f876a9', 'name' => 'Pinterest', 'desc' => null, 'icon' => 'fa-brands fa-pinterest-p', 'color' => 'primary', 'status' => true, 'created' => 0],
            ['id_secure' => '65a1059f87ad9', 'name' => 'Google Business Profile', 'desc' => null, 'icon' => 'fa-brands fa-google', 'color' => 'dark', 'status' => true, 'created' => 0],
            ['id_secure' => '65a1059f87f18', 'name' => 'TikTok', 'desc' => null, 'icon' => 'fa-brands fa-tiktok', 'color' => 'success', 'status' => true, 'created' => 0],
            ['id_secure' => '65a1059f8833e', 'name' => 'Youtube', 'desc' => null, 'icon' => 'fa-brands fa-youtube', 'color' => 'primary', 'status' => true, 'created' => 0],
            ['id_secure' => '65a1059f88814', 'name' => 'Rewrite', 'desc' => null, 'icon' => 'fa-light fa-file-signature', 'color' => 'danger', 'status' => true, 'created' => 0],
            ['id_secure' => '65a1059f88c93', 'name' => 'Edit', 'desc' => null, 'icon' => 'fa-light fa-pencil-alt', 'color' => 'warning', 'status' => true, 'created' => 0],
            ['id_secure' => '65a1059f890bc', 'name' => 'Explain & Expand', 'desc' => null, 'icon' => 'fa-light fa-expand', 'color' => 'dark', 'status' => true, 'created' => 0],
            ['id_secure' => '65a1059f894c3', 'name' => 'Summarize', 'desc' => null, 'icon' => 'fa-light fa-filter', 'color' => 'success', 'status' => true, 'created' => 0],
            ['id_secure' => '65a1059f898c7', 'name' => 'Psychological Frameworks', 'desc' => null, 'icon' => 'fa-light fa-head-side-brain', 'color' => 'info', 'status' => true, 'created' => 0],
            ['id_secure' => '65a1059f89cc3', 'name' => 'Content Creation Frameworks', 'desc' => null, 'icon' => 'fa-light fa-lightbulb-on', 'color' => 'primary', 'status' => true, 'created' => 0],
            ['id_secure' => '65a1059f8a570', 'name' => 'Ads', 'desc' => null, 'icon' => 'fa-light fa-ad', 'color' => 'danger', 'status' => true, 'created' => 0],
            ['id_secure' => '65a1059f8aa4a', 'name' => 'Company-Related', 'desc' => null, 'icon' => 'fa-light fa-building', 'color' => 'warning', 'status' => true, 'created' => 0],
            ['id_secure' => '65a1059f8aeb0', 'name' => 'CTA Prompts', 'desc' => null, 'icon' => 'fa-light fa-mouse-pointer', 'color' => 'success', 'status' => true, 'created' => 0],
            ['id_secure' => '65a1059f8b318', 'name' => 'Educational', 'desc' => null, 'icon' => 'fa-light fa-graduation-cap', 'color' => 'info', 'status' => true, 'created' => 0],
            ['id_secure' => '65a1059f8b77c', 'name' => 'Fun Prompts', 'desc' => null, 'icon' => 'fa-light fa-smile-beam', 'color' => 'primary', 'status' => true, 'created' => 0],
            ['id_secure' => '65a1059f8bc48', 'name' => 'Interactive', 'desc' => null, 'icon' => 'fa-light fa-retweet', 'color' => 'success', 'status' => true, 'created' => 0],
            ['id_secure' => '65a1059f8c08b', 'name' => 'Inspirational', 'desc' => null, 'icon' => 'fa-light fa-house-leave', 'color' => 'danger', 'status' => true, 'created' => 0],
            ['id_secure' => '65a1059f8c491', 'name' => 'Holiday', 'desc' => null, 'icon' => 'fa-light fa-umbrella-beach', 'color' => 'success', 'status' => true, 'created' => 0],
            ['id_secure' => '65a1059f8c8b7', 'name' => 'Small Businesses', 'desc' => null, 'icon' => 'fa-light fa-suitcase', 'color' => 'warning', 'status' => true, 'created' => 0],
            ['id_secure' => '65a1059f8ccb3', 'name' => 'Business Coaches', 'desc' => null, 'icon' => 'fa-light fa-user-chart', 'color' => 'warning', 'status' => true, 'created' => 0],
            ['id_secure' => '65a1059f8d0cb', 'name' => 'Lifestyle Coaches', 'desc' => null, 'icon' => 'fa-light fa-chalkboard-teacher', 'color' => 'primary', 'status' => true, 'created' => 0],
            ['id_secure' => '65a1059f8d49c', 'name' => 'Realtors', 'desc' => null, 'icon' => 'fa-light fa-house-day', 'color' => 'info', 'status' => true, 'created' => 0],
            ['id_secure' => '65a1059f8d874', 'name' => 'NGOs', 'desc' => null, 'icon' => 'fa-light fa-home-heart', 'color' => 'warning', 'status' => true, 'created' => 0],
            ['id_secure' => '65a1059f8dc3f', 'name' => 'Entreprenuers', 'desc' => null, 'icon' => 'fa-light fa-user-tie', 'color' => 'primary', 'status' => true, 'created' => 0],
            ['id_secure' => '65a1059f8e044', 'name' => 'Marketing Agencies', 'desc' => null, 'icon' => 'fa-light fa-bullseye-arrow', 'color' => 'success', 'status' => true, 'created' => 0],
            ['id_secure' => '65a1059f8e81b', 'name' => 'Authors', 'desc' => null, 'icon' => 'fa-light fa-book-reader', 'color' => 'success', 'status' => true, 'created' => 0],
            // Legacy category 33 is missing from the exported category rows, but all related templates are accounting-focused.
            ['id_secure' => 'legacyacctcat33seed01', 'name' => 'Accounting Firms', 'desc' => null, 'icon' => 'fa-light fa-calculator', 'color' => 'info', 'status' => true, 'created' => 0],
            ['id_secure' => '65a1059f8f113', 'name' => 'Restaurants', 'desc' => null, 'icon' => 'fa-light fa-utensils', 'color' => 'info', 'status' => true, 'created' => 0],
            ['id_secure' => '65a1059f8f4fa', 'name' => 'Fitness Trainers', 'desc' => null, 'icon' => 'fa-light fa-dumbbell', 'color' => 'warning', 'status' => true, 'created' => 0],
        ];

        foreach ($categories as $category) {
            AiTemplateCategory::query()->updateOrCreate(
                ['name' => $category['name']],
                array_merge($category, ['changed' => $changedAt])
            );
        }
    }
}
