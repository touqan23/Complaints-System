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

        $faker = Faker::create('ar_SA'); // استخدام اللغة العربية

        $citizens   = Citizen::all();
        $employees  = Employee::all();
        $departments = Department::where('name', '!=', 'all')->get(); // استثناء قسم all

        if ($citizens->isEmpty() || $employees->isEmpty() || $departments->isEmpty()) {
            dump("Skipping: Citizens, Employees, or Departments missing.");
            return;
        }

        // أنواع الشكاوى المناسبة للجهات الحكومية السورية
        $complaintTypes = [
            'شكوى إدارية',
            'طلب خدمة',
            'استفسار',
            'تظلم',
            'اقتراح تطوير',
            'شكوى خدمية',
            'طلب معلومات',
            'بلاغ فساد',
            'شكوى موظف',
            'تأخير معاملة',
            'رفض طلب',
            'سوء معاملة',
            'نقص خدمات',
            'مخالفة إدارية',
            'طلب تعويض'
        ];

        // مواقع سورية واقعية
        $syrianLocations = [
            'دمشق - المزة',
            'دمشق - المهاجرين',
            'دمشق - أبو رمانة',
            'دمشق - كفرسوسة',
            'دمشق - ركن الدين',
            'حلب - العزيزية',
            'حلب - الفرقان',
            'حلب - الشهباء',
            'حمص - الوعر',
            'حمص - الإنشاءات',
            'حماة - المدينة',
            'حماة - كازو',
            'اللاذقية - الزراعة',
            'اللاذقية - الرمل الشمالي',
            'طرطوس - المدينة',
            'السويداء - المدينة',
            'درعا - المدينة',
            'الحسكة - المدينة',
            'دير الزور - المدينة',
            'الرقة - المدينة',
            'إدلب - المدينة',
            'القامشلي - المدينة'
        ];

        // نصوص شكاوى واقعية بالعربية
        $complaintDescriptions = [
            'أتقدم بشكوى بخصوص تأخير معاملتي منذ أكثر من شهرين دون أي مبرر واضح، وأرجو النظر في الموضوع بشكل عاجل.',
            'تم رفض طلبي دون توضيح الأسباب بشكل كافٍ، وأرغب في معرفة الإجراءات اللازمة للتظلم.',
            'أعاني من سوء المعاملة من قبل أحد الموظفين، وأطالب بالنظر في الموضوع واتخاذ الإجراءات المناسبة.',
            'الخدمة المقدمة غير مرضية على الإطلاق، وهناك نقص واضح في الكادر الوظيفي مما يؤدي لتأخير كبير.',
            'أطالب بتحسين آلية تقديم الخدمات وتسريع الإجراءات لتوفير الوقت والجهد على المواطنين.',
            'تم إيقاف معاملتي بسبب وثائق مفقودة لم يتم إبلاغي بها مسبقاً، وهذا تسبب في ضياع وقتي.',
            'أواجه صعوبة كبيرة في الحصول على المعلومات اللازمة لإنهاء معاملتي، وأرجو المساعدة.',
            'هناك تناقض في المعلومات المقدمة من الموظفين، مما أدى إلى إرباك وتأخير في إنجاز المعاملة.',
            'أطالب بتوضيح الإجراءات المطلوبة بشكل دقيق لتجنب المراجعات المتكررة.',
            'الموقع الإلكتروني للوزارة لا يعمل بشكل صحيح، مما يعيق الحصول على الخدمات الإلكترونية.',
            'أرغب في تقديم اقتراح لتطوير آلية العمل وتسهيل الإجراءات على المواطنين.',
            'تعرضت لموقف محرج بسبب عدم تنظيم طوابير الانتظار، وأطالب بتحسين الترتيبات.',
            'الرسوم المطلوبة غير واضحة ومتفاوتة، وأحتاج لتوضيح رسمي بالمبالغ الصحيحة.',
            'أواجه مشكلة في الحصول على موعد، حيث المواعيد محجوزة لأسابيع قادمة.',
            'أطالب بتفعيل نظام الشكاوى الإلكتروني لتسهيل متابعة الشكاوى المقدمة.'
        ];

        for ($i = 0; $i < 40; $i++) {   // create 40 complaints

            $reference = $this->complaints->generateReferenceNumber();

            $complaint = Complaint::create([
                'citizen_id'        => $citizens->random()->id,
                'department_id'     => $departments->random()->id,
                'type'              => $faker->randomElement($complaintTypes),
                'location'          => $faker->randomElement($syrianLocations),
                'description'       => $faker->randomElement($complaintDescriptions),
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

            // ملاحظات واقعية بالعربية
            $noteTexts = [
                'تم استلام الشكوى وجاري دراستها من قبل القسم المختص.',
                'تم التواصل مع المواطن وطلب منه تقديم مستندات إضافية.',
                'الشكوى قيد المراجعة وسيتم الرد خلال 48 ساعة.',
                'تم تحويل الشكوى للجهة المعنية لاتخاذ الإجراء المناسب.',
                'يرجى من المواطن مراجعة القسم لاستكمال الإجراءات.',
                'تم حل المشكلة وإبلاغ المواطن بالنتيجة.',
                'الشكوى تحتاج لموافقة الإدارة العليا.',
                'جاري التنسيق مع الأقسام ذات العلاقة.',
                'تم إعداد تقرير مفصل عن الشكوى.',
                'في انتظار رد الجهة المعنية.',
                'تم إغلاق الشكوى بناءً على طلب المواطن.',
                'الشكوى مستوفية لجميع الشروط وجاري معالجتها.'
            ];

            for ($n = 0; $n < $noteCount; $n++) {
                Notes::create([
                    'complaint_id'         => $complaint->id,
                    'employee_id'          => $employees->random()->id,
                    'note'                 => $faker->randomElement($noteTexts),
                    'requested_to_citizen' => $faker->boolean(20), // 20% chance
                ]);
            }
        }
    }
}
