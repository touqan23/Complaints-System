<?php

namespace Database\Seeders;

use App\Repositories\Eloquent\ComplaintRepository;
use Illuminate\Database\Seeder;
use App\Models\Complaint;
use App\Models\ComplaintFiles;
use App\Models\Notes;
use App\Models\Citizen;
use App\Models\Employee;
use App\Models\Department;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Faker\Factory as Faker;

class ComplaintsTableSeeder extends Seeder
{
    protected ComplaintRepository $complaints;

    // Inject repository
    public function __construct(ComplaintRepository $complaints)
    {
        $this->complaints = $complaints;
    }

    public function run(): void

    {
        activity()->disableLogging();

        $faker = Faker::create();



        $citizens   = Citizen::all();
        $employees  = Employee::all();
        $departments = Department::all();

        if ($citizens->isEmpty() || $employees->isEmpty() || $departments->isEmpty()) {
            dump("Skipping: Citizens, Employees, or Departments missing.");
            return;
        }

        for ($i = 0; $i < 40; $i++) {   // create 40 complaints

            $reference = $this->complaints->generateReferenceNumber();

            $complaint = Complaint::create([
                'citizen_id'        => $citizens->random()->id,
                'department_id'     => $departments->random()->id,
                'type'              => $faker->randomElement(['Road Damage', 'Water Leak', 'Street Light', 'Waste Issue']),
                'location'          => $faker->streetAddress(),
                'description'       => $faker->paragraph(3),
                'reference_number'  => $reference,

            ]);

            /**
             * -------------------------------------------------------------
             *  Insert realistic uploaded files (images + PDF)
             * -------------------------------------------------------------
             */
            $fileCount = rand(1, 3);

            for ($f = 0; $f < $fileCount; $f++) {

                // randomly choose file type
                $fileType = $faker->randomElement(['image', 'pdf']);

                if ($fileType === 'image') {
                    // example AWS S3 URL
                    $url = Storage::disk('s3')->url("complaints/" . Str::uuid() . ".jpg");
                    $mime = "image/jpeg";

                } else {
                    $url = Storage::disk('s3')->url("complaints/" . Str::uuid() . ".pdf");
                    $mime = "application/pdf";
                }

                ComplaintFiles::create([
                    'complaint_id' => $complaint->id,
                    'url'          => $url,
                    'type'         => $mime
                ]);
            }

            /**
             * -------------------------------------------------------------
             *  Insert Notes
             * -------------------------------------------------------------
             */
            $noteCount = rand(0, 3); // 0 to 3 notes

            for ($n = 0; $n < $noteCount; $n++) {
                Notes::create([
                    'complaint_id'         => $complaint->id,
                    'employee_id'          => $employees->random()->id,
                    'note'                 => $faker->sentence(12),
                    'requested_to_citizen' => $faker->boolean(20), // 20% chance
                ]);
            }
        }
    }
}
