<?php
declare(strict_types=1);

// Regional onboarding sample profiles and starter prescription footer templates.
// Keyed by ISO 3166-1 country code, with 'INT' serving as the universal international standard.

return [
    'BD' => [
        'doctor_profile' => [
            'name_native'           => 'ডা. শাফায়েত মাহমুদ',
            'qualifications_native' => 'এমবিবিএস, এমডি (কার্ডিওলজি), এফসিপিএস (মেডিসিন), বিসিএস(স্বাস্থ্য)',
            'designation_native'    => 'চিফ কনসালটেন্ট ও বিভাগীয় প্রধান (কার্ডিওলজি)',
            'institute_native'      => 'এপেক্স কার্ডিয়াক ইনস্টিটিউট',
            'speciality_native'     => 'হৃদরোগ, উচ্চ রক্তচাপ ও মেডিসিন বিশেষজ্ঞ',
            'license_native'        => 'বিএমডিসি রেজি নং: A-112233',
            'phone_native'          => 'মোবাইলঃ ০১৭১০-XXXXXX',

            'name_en'               => 'Dr. Shafayet Mahmud',
            'qualifications_en'     => 'MBBS, MD (Cardiology), FCPS (Medicine), BCS (Health)',
            'designation_en'        => 'Chief Consultant & HOD (Cardiology)',
            'institute_en'          => 'Apex Cardiac Institute',
            'speciality_en'         => 'Cardiology, Hypertension & Medicine Specialist',
            'license_en'            => 'BMDC Reg. No: A-112233',
            'phone_en'              => 'Mobile: 01710-XXXXXX',
        ],
        'footer_html' => '<p style="text-align: center; margin: 0; line-height: 1.5;"><font face="solaimanlipi"><span style="font-size: 10pt;"><b>চেম্বারঃ</b> ZimRx ডায়াগনস্টিক এন্ড কনসালটেশন সেন্টার, ঢাকা।<br><b>চেম্বারে আসার পূর্বে সিরিয়ালঃ ০১৪০৮-XXXXXX নম্বরে যোগাযোগ করে সিরিয়াল দিবেন।</b><br><b>রোগী দেখার সময়ঃ</b> বিকাল ৪ টা থেকে রাত ৮ টা (সপ্তাহে ৬ দিন)। ওয়েবসাইটঃ www.zimrx.org</span></font></p>',
    ],

    'INT' => [
        'doctor_profile' => [
            'name_native'           => 'Dr. Alex Mercer',
            'qualifications_native' => 'MD, FACP (Internal Medicine)',
            'designation_native'    => 'Senior Consultant Physician',
            'institute_native'      => 'Department of Internal Medicine',
            'speciality_native'     => 'Internal Medicine Specialist',
            'license_native'        => 'Reg. / License No: MD-98765',
            'phone_native'          => 'Office: +1 (555) 019-2834',

            'name_en'               => 'Metropolitan Clinical Center',
            'qualifications_en'     => 'Ambulatory Care & Diagnostic Suite',
            'designation_en'        => '100 Healthcare Blvd, Suite 400',
            'institute_en'          => 'Visiting Hours: Mon - Sat, 09:00 AM - 05:00 PM',
            'speciality_en'         => 'Consultations by Prior Appointment',
            'license_en'            => 'Helpline: +1 (555) 019-2834',
            'phone_en'              => 'Portal: www.zimrx.org',
        ],
        'footer_html' => '<p style="text-align: center; margin: 0; line-height: 1.5;"><font face="tinos"><span style="font-size: 10pt;"><b>Clinic:</b> ZimRx Ambulatory Care & Consultation Suite.<br><b>Consultations by prior appointment. Helpline:</b> +1 (555) 019-2834.<br><b>Visiting Hours:</b> Mon - Sat, 09:00 AM - 05:00 PM. Portal: www.zimrx.org</span></font></p>',
    ],
];
