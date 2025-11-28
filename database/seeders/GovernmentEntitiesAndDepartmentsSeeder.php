<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GovernmentEntity;
use App\Models\Department;

class GovernmentEntitiesAndDepartmentsSeeder extends Seeder
{
    public function run(): void
    {
        // Government Entities with their related departments
        $data = [
            "Ministry of Interior" => [
                "Public Security Department",
                "Civil Status and Passports",
                "Traffic Department",
                "Complaints & Control Department",
            ],

            "Ministry of Health" => [
                "Patient Complaints Department",
                "Hospital Affairs",
                "Public Health Department",
                "Pharmacy & Drug Control",
            ],

            "Ministry of Education" => [
                "School Affairs",
                "Teachers Affairs",
                "Curriculum Department",
                "Complaints & Violations Unit",
            ],

            "Ministry of Labor" => [
                "Labor Inspection",
                "Worker Complaints Department",
                "Employment Directorate",
                "Customer Service",
            ],

            "Municipality Authority" => [
                "Sanitation Department",
                "Building Permits",
                "Public Parks Department",
                "Citizen Complaints Office",
            ],

            "Police Department" => [
                "Criminal Investigation",
                "Community Police",
                "Emergency Response Center",
                "Public Complaints Unit",
            ],
        ];

        foreach ($data as $entityName => $departments) {

            // Create Government Entity
            $entity = GovernmentEntity::create([
                'name' => $entityName,
            ]);

            // Create departments linked to that entity
            foreach ($departments as $deptName) {
                Department::create([
                    'name' => $deptName,
                    'government_entity_id' => $entity->id, // LINKING HERE
                ]);
            }
        }
    }
}
