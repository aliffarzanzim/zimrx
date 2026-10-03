// Clinical help guidelines modal controller for patient particulars, address directories, and occupation management.

function showHelpGuidelineModal(type) {
  const guidelines = {
    'pres-reg': {
      title: 'রেজিস্ট্রেশন নম্বর (Reg No) নির্দেশিকা',
      badge: 'প্রেসক্রিপশন পেজ',
      body: `
        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">১</div>
          <div class="zrx-help-step-text">
            যদি <strong>অলরেডি রেজিস্টার্ড পেশেন্ট</strong> হয় তাহলে রেজিস্ট্রেশন নম্বর লিখে সার্চ করুন বা বারকোড স্ক্যান করুন বা রেজিস্ট্রেশন নম্বর কাছে না পেলে, ফোন নম্বরের ফিল্ড থেকেও সার্চ করতে পারেন।
          </div>
        </div>
        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">২</div>
          <div class="zrx-help-step-text">
            আর যদি <strong>নতুন পেশেন্ট</strong> হয় তাহলে ব্লাঙ্ক রাখুন এবং অন্যান্য particulars পূরণ করুন বা পুরো প্রেসক্রিপশনটাই লিখে ফেলুন। <strong>Save & Print</strong> বা <strong>Save Only</strong> করার পরে অটোমেটিক রেজিস্ট্রেশন নাম্বার assign হবে।
          </div>
        </div>
      `
    },
    'appt-reg': {
      title: 'রেজিস্ট্রেশন নম্বর (Reg No) নির্দেশিকা',
      badge: 'অ্যাপয়েন্টমেন্ট পেজ',
      body: `
        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">১</div>
          <div class="zrx-help-step-text">
            যদি <strong>অলরেডি রেজিস্টার্ড পেশেন্ট</strong> হয় তাহলে রেজিস্ট্রেশন নম্বর লিখে সার্চ করুন বা বারকোড স্ক্যান করুন বা রেজিস্ট্রেশন নম্বর কাছে না পেলে, ফোন নম্বরের ফিল্ড থেকেও সার্চ করতে পারেন।
          </div>
        </div>
        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">২</div>
          <div class="zrx-help-step-text">
            আর যদি <strong>নতুন পেশেন্ট</strong> হয় তাহলে ব্লাঙ্ক রাখুন এবং অন্যান্য particulars পূরণ করুন। <strong>Save Appointment</strong> করার পরে অটোমেটিক রেজিস্ট্রেশন নাম্বার assign হবে।
          </div>
        </div>
      `
    },
    'mobile': {
      title: 'ফোন নম্বর (Mobile) নির্দেশিকা',
      badge: 'সার্চ ও রেজিস্ট্রেশন',
      body: `
        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">১</div>
          <div class="zrx-help-step-text">
            যদি <strong>অলরেডি রেজিস্টার্ড পেশেন্ট</strong> হয় তাহলে ফোন নম্বর লিখে সার্চ করুন, রেজিস্ট্রেশন নম্বর থাকলে তা দিয়েও সার্চ করতে পারেন বা বারকোড স্ক্যান করুন।
          </div>
        </div>
        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">২</div>
          <div class="zrx-help-step-text">
            আর যদি <strong>নতুন পেশেন্ট</strong> হয় তাহলে নতুন ফোন নম্বর লিখে ফেলুন, একই নম্বরে অলরেডি আরেকজনের রেজিস্ট্রেশন থেকে থাকলে ড্রপডাউন থেকে <strong>It's a new patient</strong> এ ক্লিক করুন, তাহলে একই নাম্বারে দুই পেশেন্টই রেজিস্ট্রার্ড থাকবে, তবে রেজিস্ট্রেশন নম্বর আলাদা হবে।
          </div>
        </div>
      `
    },
    'col-resize': {
      title: 'টেবিল লেআউট নির্দেশিকা',
      badge: 'কলাম রিসাইজ ও লেআউট',
      body: `
        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">১</div>
          <div class="zrx-help-step-text">
            Resize করা এই লেআউটটি সেইভ করতে <strong>Save layout</strong> করুন। এবং Default Layout এ যেতে <strong>Reset</strong> করুন।
          </div>
        </div>
      `
    },
    'dob': {
      title: 'জন্মতারিখ ও বয়স (DOB & Age) নির্দেশিকা',
      badge: 'পেশেন্ট পার্টিকুলার্স ও ক্যালকুলেশন',
      wide: true,
      body: `
        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">★</div>
          <div class="zrx-help-step-text">
            <strong>Age বা DOB যেকোনো একটি ইনপুট দিলেই হবে।</strong> যেকোনো একটি ইনপুট দিলে অন্যটি স্বয়ংক্রিয়ভাবে সেট হয়ে যাবে।
          </div>
        </div>

        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">১</div>
          <div class="zrx-help-step-text">
            <strong>DOB এন্ট্রি করলে Age ও Unit অটো-ক্যালকুলেট হবে:</strong><br>
            ক্যালেন্ডার থেকে Date of Birth সিলেক্ট করলে, সিস্টেম নিজে থেকেই রোগীর বয়স এবং সঠিক Unit নির্ধারণ করে নিবে:
            <ul class="zrx-help-list">
              <li><strong>১ বছর বা তার বেশি (&ge; 1 Year):</strong> Age দেখাবে পূর্ণ বছরে এবং <code>Unit = "Years"</code> সিলেক্ট হবে।</li>
              <li><strong>১ বছরের কম, কিন্তু ১ মাসের বেশি:</strong> Age দেখাবে মাসে এবং <code>Unit = "Months"</code> সিলেক্ট হবে।</li>
              <li><strong>১ মাসের কম, কিন্তু ৭ দিনের বেশি:</strong> Age দেখাবে সপ্তাহে এবং <code>Unit = "Weeks"</code> সিলেক্ট হবে।</li>
              <li><strong>৭ দিনের কম (নবজাতক):</strong> Age দেখাবে এক্স্যাক্ট দিনে এবং <code>Unit = "Days"</code> সিলেক্ট হবে।</li>
            </ul>
          </div>
        </div>

        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">২</div>
          <div class="zrx-help-step-text">
            <strong>Age এন্ট্রি করলে আনুমানিক DOB সেট হবে:</strong><br>
            আপনি যদি শুধু বয়স টাইপ করে Unit সিলেক্ট করেন, তবে সিস্টেম ব্যাকএন্ডে একটি আনুমানিক (Estimated) DOB তৈরি করে নিবে:
            <ul class="zrx-help-list">
              <li><strong>Years (যেমন: 25 Years):</strong> ওই হিসাবকৃত বছরের শুরুর তারিখটিকে (যেমন: ০১/০১/XXXX) DOB হিসেবে সেট করবে।</li>
              <li><strong>Months (যেমন: 6 Months):</strong> ৬ মাস আগের মাসের ১ তারিখকে DOB হিসেবে ধরবে।</li>
              <li><strong>Weeks (যেমন: 3 Weeks):</strong> বর্তমান তারিখ থেকে ২১ দিন (৩ &times; ৭) মাইনাস করে DOB সেট করবে।</li>
              <li><strong>Days (যেমন: 5 Days):</strong> বর্তমান তারিখ থেকে ৫ দিন মাইনাস করে DOB সেট করবে।</li>
            </ul>
          </div>
        </div>

        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">৩</div>
          <div class="zrx-help-step-text">
            <strong>ভবিষ্যৎ ভিজিট ও পিডিয়াট্রিক সুবিধা:</strong> পরবর্তীতে এই সেইম রোগীই আসলে ডায়নামিক ভাবে বয়স হিসাব করে প্রেস্ক্রিপশনে বসিয়ে দিবে অটোমেটিক। এছাড়া বাচ্চাদের গ্রোথ চার্ট (Growth chart) এবং ভ্যাকসিনেশনের (Vaccination) সিডিউল তৈরিতেও হেল্প করবে।
          </div>
        </div>
      `
    },
    'visit-id': {
      title: 'ভিজিট আইডি (Visit ID) নির্দেশিকা',
      badge: 'প্রেসক্রিপশন ও EMR রেকর্ড',
      body: `
        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">১</div>
          <div class="zrx-help-step-text">
            <strong>প্রেসক্রিপশন ট্র্যাকিং ও হিস্ট্রি:</strong> ভিজিট আইডিটি প্রেসক্রিপশনের সাথে জড়িত। আপনার লেখা প্রতিটি প্রেসক্রিপশনের একটি ইউনিক ভিজিট আইডি আছে যা দিয়ে এক্সাক্টলি ঐ কনসালটেশনের সময় কি প্রেসক্রিপশন করেছিলেন তা দেখতে পাবেন।
          </div>
        </div>
        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">২</div>
          <div class="zrx-help-step-text">
            <strong>স্বয়ংক্রিয় ইউনিক কোড:</strong> প্রেসক্রিপশন সংরক্ষণ (Save) করার সময় সিস্টেম নিজে থেকেই এই ইউনিক কোড তৈরি করে নেয়। এটি সম্পূর্ণরূপে স্বয়ংক্রিয় হওয়ায় এটি ম্যানুয়ালি এন্ট্রি বা পরিবর্তন করার প্রয়োজন নেই।
          </div>
        </div>
      `
    },
    'occupation': {
      title: 'পেশা (Occupation) নির্দেশিকা ও ম্যানেজমেন্ট',
      badge: 'পেশেন্ট পার্টিকুলার্স ও EMR',
      wide: true,
      body: `
        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">১</div>
          <div class="zrx-help-step-text">
            রেগুলার প্র্যাক্টিসের সময় সাধারণত Occupational History রেকর্ড করে না, ভার্বালি জেনে নেয়া হয়, অন্যান্য প্রেসক্রিপশন সফটওয়্যারেও এই ফিচারটি নেই। তবে একটি Comprehensive EMR-এর জন্য ZimRx-এ এটি অ্যাড করা হয়েছে। এর মাধ্যমে পেশেন্টের Socioeconomic অবস্থার পাশাপাশি, তার Presenting Complaints-এর সাথে কোনো Occupational Relation আছে কি না, তা সহজেই অ্যাসেস করা যাবে।
          </div>
        </div>
        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">২</div>
          <div class="zrx-help-step-text">
            আপনার সুবিধার্থে ড্রপ-ডাউন মেনুতে কিছু কমন Occupation দেওয়া আছে। চাইলে ম্যানুয়ালি টাইপ করেও নতুন Occupation এন্ট্রি করতে পারবেন। একবার এন্ট্রি করলে এটি অটো-সেইভ হয়ে থাকবে এবং পরবর্তীতে আপনার ভবিষ্যতের অন্যান্য সকল প্রেসক্রিপশনে তা Show করবে।
          </div>
        </div>
        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">৩</div>
          <div class="zrx-help-step-text">
            ড্রপ-ডাউন মেনুর sorting মেইনলি Usage এর উপর নির্ভর করবে, যেটা যত বেশি বার ইউস হয়েছে তা তত উপরে শো করবে। Usage দেখার জন্য নিচের টেবিলটি দেখুন, এখান থেকে Reset to default করতে পারবেন, ডিলিট করতে পারবেন, নতুন এন্ট্রি সরাসরি এখান থেকেই যোগ করতে পারবেন, বা কোনোটা সবসময় প্রথমে দেখাতে চাইলে pin করে রাখতে পারবেন।
          </div>
        </div>

        <div class="zrx-occ-toolbar">
          <div class="zrx-occ-add-form">
            <input type="text" id="zrx-new-occ-input" placeholder="নতুন পেশা (New Occupation) লিখুন..." autocomplete="off">
            <button type="button" id="zrx-btn-add-occ" class="zrx-btn-primary">+ Add</button>
          </div>
          <button type="button" id="zrx-btn-reset-occ" class="zrx-btn-outline" title="Reset all occupation customizations to default">Reset to Default</button>
        </div>

        <table class="zrx-occ-table">
          <thead>
            <tr>
              <th class="zrx-help-th-drag">Move</th>
              <th class="zrx-help-th-actions">Actions</th>
              <th class="zrx-help-th-sl">Type</th>
              <th class="zrx-ta-l">Occupation Name</th>
              <th class="zrx-help-th-usage">Usage</th>
            </tr>
          </thead>
          <tbody id="zrx-occ-table-body">
            <tr><td colspan="5" class="zrx-empty-cell">Loading occupations...</td></tr>
          </tbody>
        </table>
      `
    },
    'address': {
      title: 'ঠিকানা (Address) নির্দেশিকা ও ম্যানেজমেন্ট',
      badge: 'পেশেন্ট পার্টিকুলার্স ও জিওগ্রাফি',
      wide: true,
      body: `
        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">১</div>
          <div class="zrx-help-step-text">
            <strong>কমা (,) ম্যাজিক ও Smart Prediction:</strong> আপনি জায়গার নাম লিখে কমা (<code>,</code>) দিলে সিস্টেম অটোমেটিক্যালি এর পরের প্যারেন্ট লোকেশনটি বুঝে যায়। যেমন, আপনি <code>Savar</code> টাইপ করার সাথে সাথেই সিস্টেম নিজে থেকে <code>Dhaka</code> সাজেস্ট করবে।
          </div>
        </div>
        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">২</div>
          <div class="zrx-help-step-text">
            <strong>জাতীয় ডাটাবেস ও কাস্টম এড্রেস:</strong> সাজেশন লিস্টটি একসাথে দুটি জায়গা থেকে ডাটা দেখায়:
            <ul class="zrx-help-list-compact">
              <li>বাংলাদেশের সমস্ত জেলা, উপজেলা, থানা, ইউনিয়ন ও পোস্ট কোড (English &amp; বাংলা)।</li>
              <li>আপনার নিজের সেইভ করা বা বেশি ব্যবহৃত কাস্টম ঠিকানাগুলো।</li>
            </ul>
          </div>
        </div>
        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">৩</div>
          <div class="zrx-help-step-text">
            <strong>ব্যবহার অনুযায়ী স্মার্ট র‍্যাংকিং:</strong> আপনি যে ঠিকানাগুলো সবচেয়ে বেশি ব্যবহার করেন (Usage frequency) বা যেগুলো হুবহু মিলে যায় (Exact match), সেগুলো স্বয়ংক্রিয়ভাবে সাজেশনের একেবারে উপরে (Top) শো করবে।
          </div>
        </div>
        <div class="zrx-help-step">
          <div class="zrx-help-step-icon">৪</div>
          <div class="zrx-help-step-text">
            <strong>প্র্যাক্টিস জেলা ফিল্টারিং ও ম্যানেজমেন্ট:</strong> এছাড়া আপনি জাস্ট নির্দিষ্ট একটা জেলাতে প্র্যাক্টিস করতে চাইলে জাস্ট ঐ জেলা বা আশে-পাশের জেলাগুলোও সিলেক্ট করে রাখতে পারেন, তাহলে অন্যান্য জেলা আর দেখাবে না। নিচের টেবিল থেকে আপনি যায়গার নাম ডিলিট, এডিট, রিসেট, পিন এবং কাস্টম এড্রেসও যুক্ত করতে পারেন।
          </div>
        </div>

        <!-- Section A: Preferred Districts -->
        <div class="zrx-addr-section">
          <div class="zrx-addr-section-header">
            <div class="zrx-addr-section-title">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
              প্র্যাক্টিস জেলা ফিল্টার (Practice Districts Filter)
            </div>
            <div class="zrx-help-flex-gap6">
              <button type="button" id="zrx-btn-dist-all" class="zrx-btn-outline" class="zrx-help-btn-sm">All (সমগ্র বাংলাদেশ)</button>
              <button type="button" id="zrx-btn-dist-save" class="zrx-btn-primary" class="zrx-help-btn-sm-px10">Save Districts</button>
            </div>
          </div>
          <div class="zrx-district-search-bar">
            <input type="text" id="zrx-district-filter-input" placeholder="জেলা সার্চ করুন (Search District)..." autocomplete="off">
          </div>
          <div class="zrx-district-chips-wrap" id="zrx-district-chips-container">
            <span class="zrx-help-text-meta">Loading districts...</span>
          </div>
        </div>

        <!-- Section B: Custom & System Addresses Table -->
        <div class="zrx-addr-section">
          <div class="zrx-addr-section-header">
            <div class="zrx-addr-section-title">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
              ঠিকানা ডিরেক্টরি ও ম্যানেজমেন্ট (Address Directory - Top 100)
            </div>
            <button type="button" id="zrx-btn-reset-addr" class="zrx-btn-outline" class="zrx-help-btn-sm-px10" title="Reset all address customizations to default">Reset to Default</button>
          </div>

          <!-- Search & Filter Controls -->
          <div class="zrx-addr-filter-row">
            <div class="zrx-addr-search-wrap">
              <input type="text" id="zrx-addr-search-input" placeholder="ঠিকানা সার্চ করুন (Search locality, thana, upazila, district)..." autocomplete="off">
            </div>
            <div class="zrx-addr-filter-tabs" id="zrx-addr-filter-tabs-container">
              <button type="button" class="zrx-addr-tab-btn active" data-addr-filter="all">All (Top 100)</button>
              <button type="button" class="zrx-addr-tab-btn" data-addr-filter="custom">Custom Only</button>
              <button type="button" class="zrx-addr-tab-btn" data-addr-filter="system">System (National)</button>
              <button type="button" class="zrx-addr-tab-btn" data-addr-filter="pinned">Pinned</button>
            </div>
          </div>

          <div class="zrx-occ-toolbar" class="zrx-help-mt8">
            <div class="zrx-occ-add-form">
              <input type="text" id="zrx-new-addr-input" placeholder="নতুন কাস্টম ঠিকানা (New Address/Area) লিখুন..." autocomplete="off">
              <button type="button" id="zrx-btn-add-addr" class="zrx-btn-primary">+ Add</button>
            </div>
          </div>

          <table class="zrx-occ-table" class="zrx-help-mt8">
            <thead>
              <tr>
                <th class="zrx-help-th-actions-alt">Actions</th>
                <th class="zrx-help-th-sl-alt">Type</th>
                <th class="zrx-ta-l">Address / Combination</th>
                <th class="zrx-help-th-usage">Usage</th>
              </tr>
            </thead>
            <tbody id="zrx-addr-table-body">
              <tr><td colspan="4" class="zrx-empty-cell">Loading addresses...</td></tr>
            </tbody>
          </table>
        </div>
      `
    }
  };

  const data = guidelines[type];
  if (!data) return;

  let overlay = document.getElementById('zrx-help-modal-overlay');
  if (!overlay) {
    overlay = document.createElement('div');
    overlay.id = 'zrx-help-modal-overlay';
    overlay.className = 'zrx-help-modal-overlay';
    overlay.innerHTML = `
      <div class="zrx-help-modal-card" id="zrx-help-modal-card" role="dialog" aria-modal="true">
        <div class="zrx-help-modal-header">
          <div class="zrx-help-modal-title-wrap">
            <span class="zrx-help-modal-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            </span>
            <div>
              <h3 id="zrx-help-modal-title"></h3>
              <span id="zrx-help-modal-badge" class="zrx-help-modal-badge"></span>
            </div>
          </div>
          <button type="button" class="zrx-help-modal-close" id="zrx-help-modal-close-btn" aria-label="Close">✕</button>
        </div>
        <div class="zrx-help-modal-body" id="zrx-help-modal-body"></div>
        <div class="zrx-help-modal-footer">
          <button type="button" class="zrx-help-modal-btn" id="zrx-help-modal-ok-btn">বুঝেছি</button>
        </div>
      </div>
    `;
    document.body.appendChild(overlay);

    const closeHelp = () => {
      overlay.classList.remove('active');
    };

    overlay.querySelector('#zrx-help-modal-close-btn').addEventListener('click', closeHelp);
    overlay.querySelector('#zrx-help-modal-ok-btn').addEventListener('click', closeHelp);
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) closeHelp();
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && overlay.classList.contains('active')) closeHelp();
    });
  }

  const card = overlay.querySelector('#zrx-help-modal-card');
  if (card) {
    if (data.wide) card.classList.add('zrx-help-modal-wide');
    else card.classList.remove('zrx-help-modal-wide');
  }

  document.getElementById('zrx-help-modal-title').textContent = data.title;
  document.getElementById('zrx-help-modal-badge').textContent = data.badge;
  // SAFETY: data.body is always a developer-defined static string from the hardcoded
  // `guidelines` object above. NEVER assign server-fetched or user-supplied content
  // here without DOMPurify sanitization first.
  document.getElementById('zrx-help-modal-body').innerHTML = data.body;
  overlay.classList.add('active');

  if (type === 'occupation') {
    const escapeHtml = (value) => {
      const div = document.createElement('div');
      div.textContent = value == null ? '' : String(value);
      return div.innerHTML;
    };

    let draggingRow = null;

    const loadOccupationTable = () => {
      const tbody = document.getElementById('zrx-occ-table-body');
      if (!tbody) return;

      fetch('api/occupation_settings.php?action=list')
        .then(res => res.json())
        .then(resData => {
          const list = resData.occupations || [];
          if (!list.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="zrx-empty-cell">No occupations found.</td></tr>';
            return;
          }

          tbody.innerHTML = list.map(occ => {
            const isPinned = Number(occ.is_pinned) === 1;
            const isHidden = Number(occ.is_hidden) === 1;
            const kind = escapeHtml(String(occ.kind ?? ''));
            const name = escapeHtml(String(occ.name ?? ''));
            const isSystem = occ.kind === 'system';
            const moveIcon = typeof ZimRxIcon !== 'undefined' 
              ? ZimRxIcon.render('move', 14) 
              : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="5 9 2 12 5 15"></polyline><polyline points="9 5 12 2 15 5"></polyline><polyline points="19 9 22 12 19 15"></polyline><polyline points="9 19 12 22 15 19"></polyline><line x1="2" y1="12" x2="22" y2="12"></line><line x1="12" y1="2" x2="12" y2="22"></line></svg>';
            
            return `
              <tr class="pc-row phrase-row ${isPinned ? 'pinned' : ''} ${isHidden ? 'hidden' : ''}" data-name="${name}" draggable="true">
                <td class="pc-action pc-move phrase-handle" class="zrx-help-cell-drag">
                  ${isPinned ? '<img class="phrase-handle-pin" src="assets/images/pin.svg" alt="Pinned" class="zrx-help-pin-img">' : ''}
                  <button type="button" class="pc-row-move-btn zrx-drag-handle" title="Move Row">${moveIcon}</button>
                </td>
                <td class="zrx-ta-c">
                  <div class="phrase-actions" class="zrx-help-actions-flex">
                    <button type="button" class="phrase-btn ${isPinned ? 'active' : ''}" data-occ-action="pin" data-name="${name}" class="${isPinned ? 'zrx-help-pin-btn-active' : ''}">${isPinned ? 'Unpin' : 'Pin'}</button>
                    <button type="button" class="phrase-btn" data-occ-action="edit" data-name="${name}">Edit</button>
                    ${isSystem
                      ? (isHidden
                          ? `<button type="button" class="phrase-btn primary" data-occ-action="toggle_hide" data-name="${name}">Restore</button>`
                          : `<button type="button" class="phrase-btn danger" data-occ-action="toggle_hide" data-name="${name}">Remove</button>`)
                      : `<button type="button" class="phrase-btn danger" data-occ-action="delete" data-name="${name}">Delete</button>`
                    }
                  </div>
                </td>
                <td class="zrx-ta-c">
                  <div class="phrase-tags" class="zrx-help-justify-center">
                    <span class="phrase-tag ${isSystem ? 'system' : (kind || 'custom')}">${isSystem ? 'System' : (kind ? kind.charAt(0).toUpperCase() + kind.slice(1) : 'Custom')}</span>
                    ${isHidden ? '<span class="phrase-tag hidden" class="zrx-help-badge-danger">Hidden</span>' : ''}
                  </div>
                </td>
                <td>
                  <div class="phrase-text" class="${isHidden ? 'zrx-help-row-hidden' : 'zrx-help-row-visible'}">${name}</div>
                </td>
                <td class="phrase-usage" class="zrx-help-usage-val">
                  ${Number(occ.usage_count || 0)}
                </td>
              </tr>
            `;
          }).join('');

          if (typeof window.refreshOccupations === 'function') {
            window.refreshOccupations();
          }

          // Bind Action Buttons
          tbody.querySelectorAll('[data-occ-action="pin"]').forEach(btn => {
            btn.onclick = () => {
              const name = btn.getAttribute('data-name');
              fetch('api/occupation_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'toggle_pin', name })
              }).then(() => loadOccupationTable());
            };
          });

          tbody.querySelectorAll('[data-occ-action="edit"]').forEach(btn => {
            btn.onclick = () => {
              const name = btn.getAttribute('data-name');
              const newName = prompt('Edit occupation name:', name);
              if (newName && newName.trim() && newName.trim() !== name) {
                fetch('api/occupation_settings.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ action: 'edit', name, new_name: newName.trim() })
                }).then(() => loadOccupationTable());
              }
            };
          });

          tbody.querySelectorAll('[data-occ-action="toggle_hide"]').forEach(btn => {
            btn.onclick = () => {
              const name = btn.getAttribute('data-name');
              fetch('api/occupation_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'toggle_hide', name })
              }).then(() => loadOccupationTable());
            };
          });

          tbody.querySelectorAll('[data-occ-action="delete"]').forEach(btn => {
            btn.onclick = () => {
              const name = btn.getAttribute('data-name');
              if (confirm(`Are you sure you want to delete "${name}"?`)) {
                fetch('api/occupation_settings.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ action: 'delete', name })
                }).then(() => loadOccupationTable());
              }
            };
          });

          // Row drag-and-drop
          tbody.querySelectorAll('tr.phrase-row').forEach(row => {
            row.addEventListener('dragstart', (e) => {
              draggingRow = row;
              e.dataTransfer.effectAllowed = 'move';
              row.classList.add('dragging');
            });

            row.addEventListener('dragover', (e) => {
              e.preventDefault();
              e.dataTransfer.dropEffect = 'move';
              const targetRow = e.target.closest('tr.phrase-row');
              if (targetRow && targetRow !== draggingRow) {
                const rect = targetRow.getBoundingClientRect();
                const next = (e.clientY - rect.top) / (rect.bottom - rect.top) > 0.5;
                tbody.insertBefore(draggingRow, next ? targetRow.nextSibling : targetRow);
              }
            });

            row.addEventListener('dragend', () => {
              if (draggingRow) {
                draggingRow.classList.remove('dragging');
                draggingRow = null;
                tbody.dispatchEvent(new CustomEvent('zrx:reordered'));
              }
            });
          });

          tbody.onreorder = () => {

            fetch('api/occupation_settings.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ action: 'reorder', names })
            }).then(() => {
              if (typeof window.refreshOccupations === 'function') {
                window.refreshOccupations();
              }
            });
          };

          tbody.addEventListener('zrx:reordered', tbody.onreorder);
        })
        .catch(() => {
          tbody.innerHTML = '<tr><td colspan="5" class="zrx-empty-cell-err">Failed to load occupations.</td></tr>';
        });
    };

    loadOccupationTable();

    const addBtn = overlay.querySelector('#zrx-btn-add-occ');
    const addInput = overlay.querySelector('#zrx-new-occ-input');
    if (addBtn && addInput) {
      const doAdd = () => {
        const name = addInput.value.trim();
        if (!name) return;
        fetch('api/occupation_settings.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'add', name })
        }).then(() => {
          addInput.value = '';
          loadOccupationTable();
        });
      };
      addBtn.onclick = doAdd;
      addInput.onkeydown = (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          doAdd();
        }
      };
    }

    const resetBtn = overlay.querySelector('#zrx-btn-reset-occ');
    if (resetBtn) {
      resetBtn.onclick = () => {
        if (confirm('Reset all occupation customizations to default? This will unpin, unhide, and restore all system occupations.')) {
          fetch('api/occupation_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'reset' })
          }).then(() => loadOccupationTable());
        }
      };
    }
  }

  if (type === 'address') {
    const escapeHtml = (value) => {
      const div = document.createElement('div');
      div.textContent = value == null ? '' : String(value);
      return div.innerHTML;
    };

    let allDistricts = [];
    let selectedDistrictNames = [];
    let currentFilter = 'all';
    let currentSearchQ = '';
    let searchDebounceTimer = null;

    const loadAddressSettings = () => {
      const tbody = document.getElementById('zrx-addr-table-body');
      if (tbody) {
        tbody.innerHTML = '<tr><td colspan="4" class="zrx-empty-cell">Loading addresses (Top 100)...</td></tr>';
      }

      const url = `api/address_settings.php?action=list&q=${encodeURIComponent(currentSearchQ)}&filter=${encodeURIComponent(currentFilter)}`;

      fetch(url)
        .then(res => res.json())
        .then(resData => {
          if (!resData.ok) return;

          // Render district filter chips if not loaded yet
          if (!allDistricts.length) {
            allDistricts = resData.districts || [];
            selectedDistrictNames = resData.preferred_districts || [];
            renderDistrictChips();
          }

          // Render custom and system address records (top 100)
          const list = resData.addresses || [];
          if (!tbody) return;

          if (!list.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="zrx-empty-cell">No matching addresses found. Try typing another search keyword or add a new custom address.</td></tr>';
            return;
          }

          tbody.innerHTML = list.map(addr => {
            const isPinned = Number(addr.is_pinned) === 1;
            const isHidden = Number(addr.is_hidden) === 1;
            const kind = escapeHtml(String(addr.kind ?? ''));
            const name = escapeHtml(String(addr.name ?? ''));
            const isSystem = addr.kind === 'system';

            const actionsHtml = isSystem
              ? `<span class="zrx-help-italic-muted">National Database</span>`
              : `
                <div class="phrase-actions" class="zrx-help-actions-flex">
                  <button type="button" class="phrase-btn ${isPinned ? 'active' : ''}" data-addr-action="pin" data-name="${name}" class="${isPinned ? 'zrx-help-pin-btn-active' : ''}">${isPinned ? 'Unpin' : 'Pin'}</button>
                  <button type="button" class="phrase-btn" data-addr-action="edit" data-name="${name}">Edit</button>
                  <button type="button" class="phrase-btn ${isHidden ? 'primary' : ''}" data-addr-action="toggle_hide" data-name="${name}">${isHidden ? 'Unhide' : 'Hide'}</button>
                  <button type="button" class="phrase-btn danger" data-addr-action="delete" data-name="${name}">Delete</button>
                </div>
              `;

            return `
              <tr class="pc-row ${isPinned ? 'pinned' : ''} ${isHidden ? 'hidden' : ''}">
                <td class="zrx-ta-c">
                  ${actionsHtml}
                </td>
                <td class="zrx-ta-c">
                  <div class="phrase-tags" class="zrx-help-justify-center">
                    <span class="phrase-tag ${isSystem ? 'system' : (kind || 'custom')}">${isSystem ? 'System' : (kind ? kind.charAt(0).toUpperCase() + kind.slice(1) : 'Custom')}</span>
                    ${isHidden ? '<span class="phrase-tag hidden" class="zrx-help-badge-danger">Hidden</span>' : ''}
                  </div>
                </td>
                <td>
                  <div class="phrase-text" class="${isHidden ? 'zrx-help-row-hidden' : 'zrx-help-row-visible'}">${name}</div>
                </td>
                <td class="zrx-help-usage-val">
                  ${isSystem ? 'N/A' : Number(addr.usage_count || 0)}
                </td>
              </tr>
            `;
          }).join('');

          // Bind Custom Address Actions
          tbody.querySelectorAll('[data-addr-action="pin"]').forEach(btn => {
            btn.onclick = () => {
              const name = btn.getAttribute('data-name');
              fetch('api/address_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'toggle_pin', name })
              }).then(() => loadAddressSettings());
            };
          });

          tbody.querySelectorAll('[data-addr-action="edit"]').forEach(btn => {
            btn.onclick = () => {
              const name = btn.getAttribute('data-name');
              const newName = prompt('Edit address/locality name:', name);
              if (newName && newName.trim() && newName.trim() !== name) {
                fetch('api/address_settings.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ action: 'edit', name, new_name: newName.trim() })
                }).then(() => loadAddressSettings());
              }
            };
          });

          tbody.querySelectorAll('[data-addr-action="toggle_hide"]').forEach(btn => {
            btn.onclick = () => {
              const name = btn.getAttribute('data-name');
              fetch('api/address_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'toggle_hide', name })
              }).then(() => loadAddressSettings());
            };
          });

          tbody.querySelectorAll('[data-addr-action="delete"]').forEach(btn => {
            btn.onclick = () => {
              const name = btn.getAttribute('data-name');
              if (confirm(`Are you sure you want to delete "${name}"?`)) {
                fetch('api/address_settings.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ action: 'delete', name })
                }).then(() => loadAddressSettings());
              }
            };
          });
        })
        .catch(() => {
          if (tbody) tbody.innerHTML = '<tr><td colspan="4" class="zrx-empty-cell-err">Failed to load address settings.</td></tr>';
        });
    };

    const renderDistrictChips = () => {
      const chipsWrap = document.getElementById('zrx-district-chips-container');
      const searchInput = document.getElementById('zrx-district-filter-input');
      const filterText = (searchInput?.value || '').toLowerCase().trim();
      if (!chipsWrap) return;

      const filtered = allDistricts.filter(d => {
        if (!filterText) return true;
        return d.name.toLowerCase().includes(filterText) || (d.bn_name && d.bn_name.toLowerCase().includes(filterText));
      });

      if (!filtered.length) {
        chipsWrap.innerHTML = '<span class="zrx-help-muted-p4">No matching districts.</span>';
        return;
      }

      chipsWrap.innerHTML = filtered.map(d => {
        const isSel = selectedDistrictNames.length === 0 || selectedDistrictNames.includes(d.name);
        return `
          <span class="zrx-district-chip ${isSel ? 'selected' : ''}" data-dist-name="${escapeHtml(d.name)}">
            ${escapeHtml(d.name)}${d.bn_name ? ` (${escapeHtml(d.bn_name)})` : ''}
            ${isSel ? '✓' : '+'}
          </span>
        `;
      }).join('');

      chipsWrap.querySelectorAll('.zrx-district-chip').forEach(chip => {
        chip.onclick = () => {
          const dName = chip.getAttribute('data-dist-name');
          if (selectedDistrictNames.length === 0) {
            selectedDistrictNames = [dName];
          } else if (selectedDistrictNames.includes(dName)) {
            selectedDistrictNames = selectedDistrictNames.filter(n => n !== dName);
          } else {
            selectedDistrictNames.push(dName);
          }
          renderDistrictChips();
        };
      });
    };

    const distSearchInput = overlay.querySelector('#zrx-district-filter-input');
    if (distSearchInput) {
      distSearchInput.oninput = () => renderDistrictChips();
    }

    const distAllBtn = overlay.querySelector('#zrx-btn-dist-all');
    if (distAllBtn) {
      distAllBtn.onclick = () => {
        selectedDistrictNames = [];
        renderDistrictChips();
      };
    }

    const distSaveBtn = overlay.querySelector('#zrx-btn-dist-save');
    if (distSaveBtn) {
      distSaveBtn.onclick = () => {
        distSaveBtn.disabled = true;
        distSaveBtn.textContent = 'Saving...';
        fetch('api/address_settings.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'save_districts', districts: selectedDistrictNames })
        })
        .then(res => res.json())
        .then(data => {
          distSaveBtn.disabled = false;
          if (data.ok) {
            distSaveBtn.textContent = 'Saved ✓';
            setTimeout(() => { distSaveBtn.textContent = 'Save Districts'; }, 2000);
          }
        })
        .catch(() => {
          distSaveBtn.disabled = false;
          distSaveBtn.textContent = 'Save Districts';
        });
      };
    }

    // Address Search with Debounce
    const addrSearchInput = overlay.querySelector('#zrx-addr-search-input');
    if (addrSearchInput) {
      addrSearchInput.oninput = () => {
        clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(() => {
          currentSearchQ = addrSearchInput.value.trim();
          loadAddressSettings();
        }, 250);
      };
    }

    // Address Filter Tabs
    const filterTabsContainer = overlay.querySelector('#zrx-addr-filter-tabs-container');
    if (filterTabsContainer) {
      filterTabsContainer.querySelectorAll('.zrx-addr-tab-btn').forEach(tabBtn => {
        tabBtn.onclick = () => {
          filterTabsContainer.querySelectorAll('.zrx-addr-tab-btn').forEach(b => b.classList.remove('active'));
          tabBtn.classList.add('active');
          currentFilter = tabBtn.getAttribute('data-addr-filter') || 'all';
          loadAddressSettings();
        };
      });
    }

    const addAddrBtn = overlay.querySelector('#zrx-btn-add-addr');
    const addAddrInput = overlay.querySelector('#zrx-new-addr-input');
    if (addAddrBtn && addAddrInput) {
      const doAdd = () => {
        const name = addAddrInput.value.trim();
        if (!name) return;
        fetch('api/address_settings.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'add', name })
        }).then(() => {
          addAddrInput.value = '';
          loadAddressSettings();
        });
      };
      addAddrBtn.onclick = doAdd;
      addAddrInput.onkeydown = (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          doAdd();
        }
      };
    }

    const resetAddrBtn = overlay.querySelector('#zrx-btn-reset-addr');
    if (resetAddrBtn) {
      resetAddrBtn.onclick = () => {
        if (confirm('Reset all address customizations and district preferences to default?')) {
          fetch('api/address_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'reset' })
          }).then(() => {
            allDistricts = [];
            selectedDistrictNames = [];
            loadAddressSettings();
          });
        }
      };
    }

    loadAddressSettings();
  }
}

function initializeHelpGuidelineModals() {
  const handleHelpTrigger = (e) => {
    const btn = e.target?.closest?.('.zrx-help-icon-btn');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
    const type = btn.getAttribute('data-help-type');
    if (type) {
      showHelpGuidelineModal(type);
    }
  };

  document.addEventListener('click', handleHelpTrigger, true);
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') {
      const btn = e.target?.closest?.('.zrx-help-icon-btn');
      if (btn) {
        handleHelpTrigger(e);
      }
    }
  }, true);
}

