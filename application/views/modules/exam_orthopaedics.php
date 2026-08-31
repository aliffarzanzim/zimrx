<?php
declare(strict_types=1);

// Orthopaedics module: spine ROM, radiculopathy/SLR, major joints, and rheumatology scoring.
?>
<div class="pc-wrapper zrx-exam-wrapper" id="ortho-wrapper">
    <div class="module-header zrx-exam-header">
        <span class="zrx-exam-title-group">
            <?= zrx_icon('bone', 14) ?>
            <span>Orthopaedics &amp; Musculoskeletal Exam</span>
        </span>
        <div class="zrx-exam-actions">
            <button type="button" class="zrx-exam-btn zrx-exam-btn-normal" data-action="normal-ortho" title="Pre-fill normal MSK findings">Normal MSK</button>
            <button type="button" class="zrx-exam-btn zrx-exam-btn-clear" data-action="clear-ortho" title="Clear all orthopaedic fields">Clear</button>
        </div>
    </div>

    <div class="zrx-exam-body">
        <!-- Sub-mode Selector for Ortho/MSK Regions -->
        <div class="zrx-exam-mode-nav">
            <button type="button" class="zrx-exam-mode-tab active" data-ortho-tab="spine">
                <?= zrx_icon('activity', 13) ?> 1. Spine &amp; Gait
            </button>
            <button type="button" class="zrx-exam-mode-tab" data-ortho-tab="upper">
                <?= zrx_icon('user', 13) ?> 2. Shoulder, Elbow &amp; Hand
            </button>
            <button type="button" class="zrx-exam-mode-tab" data-ortho-tab="lower">
                <?= zrx_icon('grid', 13) ?> 3. Hip, Knee, Ankle &amp; Foot
            </button>
            <button type="button" class="zrx-exam-mode-tab" data-ortho-tab="rheum">
                <?= zrx_icon('shield', 13) ?> 4. Rheumatology &amp; Joint Count
            </button>
        </div>

        <!-- Section 1: Spine & Gait -->
        <div class="zrx-ortho-pane active" id="zrx-ortho-pane-spine">
            <div class="zrx-exam-dual-grid">
                <!-- Gait & Cervical Spine Column -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">GAIT &amp; C-SPINE</span>
                        <span>Gait &amp; Cervical Spine</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Gait Assessment</label>
                        <select class="zrx-exam-select" data-ortho-field="gait">
                            <option value="Normal steady, reciprocal gait">Normal steady gait</option>
                            <option value="Antalgic gait (Shortened stance phase)">Antalgic (Painful limp)</option>
                            <option value="Trendelenburg gait (Abductor weakness)">Trendelenburg gait</option>
                            <option value="High-stepping / Foot drop gait">High-stepping (Foot drop)</option>
                            <option value="Waddling gait (Proximal myopathy)">Waddling gait</option>
                            <option value="Broad-based ataxic gait">Broad-based ataxic</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Cervical Spine ROM &amp; Provocative Signs</label>
                        <select class="zrx-exam-select" data-ortho-field="cervical-spine">
                            <option value="Full pain-free ROM, Spurling test negative">Full pain-free ROM (Normal)</option>
                            <option value="Paraspinal muscle spasm with restricted lateral flexion">Paraspinal muscle spasm</option>
                            <option value="Spurling test positive (Cervical radiculopathy)">Spurling test +ve (Radiculopathy)</option>
                            <option value="Lhermitte sign positive (Electric shock sensation)">Lhermitte sign positive</option>
                            <option value="Severe global restriction of cervical movements">Severe global restriction</option>
                        </select>
                    </div>
                </div>

                <!-- Lumbar Spine & Radiculopathy Column -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">L-SPINE &amp; SLR</span>
                        <span>Lumbar Spine &amp; Nerve Roots</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Lumbar Spine &amp; Posture</label>
                        <select class="zrx-exam-select" data-ortho-field="lumbar-spine">
                            <option value="Normal lumbar lordosis, full pain-free ROM">Normal lordosis, full ROM</option>
                            <option value="Loss of lumbar lordosis with paraspinal spasm">Loss of lordosis / Spasm</option>
                            <option value="Sciatic scoliosis / List present">Sciatic list / Scoliosis</option>
                            <option value="Spinous process tenderness (Localised)">Spinous process tenderness</option>
                            <option value="Restricted forward flexion & extension">Restricted flexion/extension</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Straight Leg Raise (SLR) &amp; Lasegue Test</label>
                        <select class="zrx-exam-select" data-ortho-field="slr-test">
                            <option value="Bilateral SLR >80° without radicular pain (Negative)">Bilateral SLR >80° (Negative)</option>
                            <option value="Right SLR positive at 30° with radiating nerve pain">Right SLR +ve at 30° (Severe)</option>
                            <option value="Right SLR positive at 45° with radiating nerve pain">Right SLR +ve at 45° (Moderate)</option>
                            <option value="Left SLR positive at 30° with radiating nerve pain">Left SLR +ve at 30° (Severe)</option>
                            <option value="Left SLR positive at 45° with radiating nerve pain">Left SLR +ve at 45° (Moderate)</option>
                            <option value="Crossed / Well-leg raise positive (Disc herniation)">Well-leg raise +ve (Disc herniation)</option>
                            <option value="Femoral nerve stretch test positive (L2-L4)">Femoral stretch +ve (L2-L4)</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Sacroiliac (SI) Joint Tests</label>
                        <select class="zrx-exam-select" data-ortho-field="si-joint">
                            <option value="FABER/Patrick test negative, non-tender SI joints">FABER test negative (Normal)</option>
                            <option value="Right FABER test positive (Right sacroiliitis)">Right FABER +ve (Right SI pain)</option>
                            <option value="Left FABER test positive (Left sacroiliitis)">Left FABER +ve (Left SI pain)</option>
                            <option value="Bilateral SI joint tenderness on pelvic compression">Bilateral SI compression pain</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2: Shoulder, Elbow, Wrist & Hand -->
        <div class="zrx-ortho-pane" id="zrx-ortho-pane-upper" hidden>
            <div class="zrx-exam-dual-grid">
                <!-- Shoulder Column -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">SHOULDER</span>
                        <span>Shoulder Joint &amp; Rotator Cuff</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Shoulder ROM &amp; Capsular Pattern</label>
                        <select class="zrx-exam-select" data-ortho-field="shoulder-rom">
                            <option value="Full active and passive ROM bilaterally">Full active &amp; passive ROM</option>
                            <option value="Painful Arc positive (Subacromial impingement 60°-120°)">Painful Arc positive (60°-120°)</option>
                            <option value="Frozen shoulder (Capsular restriction: External rotation > Abduction)">Frozen shoulder / Capsulitis</option>
                            <option value="Right shoulder painful abduction restricted to 90°">Right shoulder restricted (90°)</option>
                            <option value="Left shoulder painful abduction restricted to 90°">Left shoulder restricted (90°)</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Rotator Cuff &amp; Impingement Tests</label>
                        <select class="zrx-exam-select" data-ortho-field="shoulder-cuff">
                            <option value="Neer, Hawkins-Kennedy & Jobe empty-can tests negative">Rotator cuff tests negative</option>
                            <option value="Supraspinatus tear / tendinopathy (Empty-can / Jobe +ve)">Supraspinatus / Jobe +ve</option>
                            <option value="Subacromial impingement (Neer & Hawkins +ve)">Neer &amp; Hawkins +ve</option>
                            <option value="Subscapularis tear (Gerber lift-off / Belly-press +ve)">Subscapularis lift-off +ve</option>
                            <option value="Biceps tendinopathy (Speed & Yergason tests +ve)">Biceps / Speed test +ve</option>
                        </select>
                    </div>
                </div>

                <!-- Elbow, Wrist & Hand Column -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">ELBOW &amp; HAND</span>
                        <span>Elbow, Wrist &amp; Hand Examination</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Elbow Epicondyles &amp; Tendons</label>
                        <select class="zrx-exam-select" data-ortho-field="elbow">
                            <option value="Normal carrying angle, non-tender epicondyles">Normal non-tender elbow</option>
                            <option value="Lateral epicondylitis (Tennis elbow / Cozen test +ve)">Tennis elbow (Lateral / Cozen +ve)</option>
                            <option value="Medial epicondylitis (Golfer's elbow tenderness)">Golfer's elbow (Medial)</option>
                            <option value="Olecranon bursitis (Fluctuant swelling over tip)">Olecranon bursitis</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Wrist &amp; Nerve Entrapment Tests</label>
                        <select class="zrx-exam-select" data-ortho-field="wrist-hand">
                            <option value="Phalen, Tinel, and Finkelstein tests negative">Tests negative (Normal wrist/hand)</option>
                            <option value="Carpal Tunnel Syndrome (Phalen & Tinel +ve over median nerve)">Carpal Tunnel (Phalen &amp; Tinel +ve)</option>
                            <option value="De Quervain's tenosynovitis (Finkelstein test +ve)">De Quervain (Finkelstein +ve)</option>
                            <option value="Guyon canal syndrome (Ulnar nerve Tinel +ve at wrist)">Guyon canal / Ulnar neuropathy</option>
                            <option value="Ganglion cyst over dorsum of wrist">Ganglion cyst (Dorsum)</option>
                            <option value="Trigger finger / Stenosing flexor tenosynovitis">Trigger finger palpable nodule</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Joint Deformities &amp; Nodes</label>
                        <select class="zrx-exam-select" data-ortho-field="hand-deformity">
                            <option value="No joint swelling, nodes, or deformities">No deformities or nodes</option>
                            <option value="Heberden nodes (DIP) & Bouchard nodes (PIP) - Osteoarthritis">Heberden &amp; Bouchard nodes (OA)</option>
                            <option value="Ulnar deviation of MCP joints (Rheumatoid hand)">Ulnar deviation (Rheumatoid)</option>
                            <option value="Swan-neck / Boutonniere deformities present">Swan-neck / Boutonniere</option>
                            <option value="Dupuytren contracture (Palmar fascial thickening)">Dupuytren contracture</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 3: Hip, Knee, Ankle & Foot -->
        <div class="zrx-ortho-pane" id="zrx-ortho-pane-lower" hidden>
            <div class="zrx-exam-dual-grid">
                <!-- Hip & Knee Column -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">HIP &amp; KNEE</span>
                        <span>Hip &amp; Knee Joints</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Hip Joint &amp; Abductor Function</label>
                        <select class="zrx-exam-select" data-ortho-field="hip-joint">
                            <option value="Normal hip ROM, Thomas test negative, Trendelenburg negative">Normal hip ROM &amp; stability</option>
                            <option value="Fixed flexion deformity (Thomas test positive)">Thomas test +ve (Fixed flexion)</option>
                            <option value="Trendelenburg test positive (Gluteus medius weakness)">Trendelenburg +ve (Abductor weak)</option>
                            <option value="Painful internal rotation & groin pain (Hip OA)">Painful internal rotation / OA</option>
                            <option value="Trochanteric bursitis (Point tenderness over greater trochanter)">Trochanteric bursitis</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Knee Joint Effusion &amp; Degeneration</label>
                        <select class="zrx-exam-select" data-ortho-field="knee-effusion">
                            <option value="No effusion, patellar tap negative, no crepitus">No effusion, no crepitus (Normal)</option>
                            <option value="Moderate effusion (Patellar tap positive)">Patellar tap +ve (Effusion)</option>
                            <option value="Coarse crepitus during active & passive flexion (OA Knee)">Coarse crepitus (Osteoarthritis)</option>
                            <option value="Medial joint line tenderness & varus deformity">Medial joint line pain (Genu varum)</option>
                            <option value="Lateral joint line tenderness & valgus deformity">Lateral joint line pain (Genu valgum)</option>
                            <option value="Popliteal / Baker cyst palpable in popliteal fossa">Baker cyst in popliteal fossa</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Knee Ligament &amp; Meniscal Tests</label>
                        <select class="zrx-exam-select" data-ortho-field="knee-ligaments">
                            <option value="Lachman, Drawer, Collaterals, and McMurray negative">Ligaments &amp; Menisci intact (Normal)</option>
                            <option value="Medial meniscus tear (McMurray test positive medially)">McMurray test +ve (Medial meniscus)</option>
                            <option value="Lateral meniscus tear (McMurray test positive laterally)">McMurray test +ve (Lateral meniscus)</option>
                            <option value="Anterior cruciate ligament (ACL) injury (Lachman & Anterior drawer +ve)">ACL injury (Lachman +ve)</option>
                            <option value="Posterior cruciate ligament (PCL) injury (Posterior sag & drawer +ve)">PCL injury (Posterior sag +ve)</option>
                            <option value="Medial collateral ligament (MCL) laxity on valgus stress">MCL laxity (Valgus stress +ve)</option>
                        </select>
                    </div>
                </div>

                <!-- Ankle & Foot Column -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">ANKLE &amp; FOOT</span>
                        <span>Ankle, Achilles &amp; Plantar Assessment</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Ankle Stability &amp; Ligaments</label>
                        <select class="zrx-exam-select" data-ortho-field="ankle-stability">
                            <option value="Stable ankle mortise, anterior drawer negative">Stable ankle, no ligament laxity</option>
                            <option value="Anterior talofibular ligament (ATFL) sprain / drawer +ve">ATFL sprain / Anterior drawer +ve</option>
                            <option value="Syndesmotic injury / High ankle sprain (Squeeze test +ve)">High ankle sprain (Squeeze +ve)</option>
                            <option value="Bimalleolar / Trimalleolar fracture tenderness">Malleolar fracture tenderness</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Achilles Tendon &amp; Plantar Fascia</label>
                        <select class="zrx-exam-select" data-ortho-field="foot-achilles">
                            <option value="Achilles tendon intact, non-tender plantar fascia">Achilles intact, non-tender fascia</option>
                            <option value="Plantar fasciitis (Pinpoint medial calcaneal tubercle tenderness)">Plantar fasciitis (Calcaneal pain)</option>
                            <option value="Achilles tendinopathy (Fusiform swelling & tenderness)">Achilles tendinopathy</option>
                            <option value="Achilles tendon rupture (Thompson calf squeeze test positive)">Thompson test +ve (Achilles rupture)</option>
                            <option value="Morton neuroma (Mulder click & intermetatarsal tenderness)">Morton neuroma (Mulder click +ve)</option>
                            <option value="Hallux valgus with bunion deformity">Hallux valgus with bunion</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 4: Rheumatology & Joint Count -->
        <div class="zrx-ortho-pane" id="zrx-ortho-pane-rheum" hidden>
            <div class="zrx-exam-dual-grid">
                <!-- Joint Count & Activity Column -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">ARTHRITIS</span>
                        <span>Synovitis &amp; Joint Count</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Tender Joint Count (TJC)</label>
                        <select class="zrx-exam-select" data-ortho-field="tjc">
                            <option value="0 (No tender joints)">0 (No tender joints - Remission)</option>
                            <option value="1 to 3 tender joints (Mild disease activity)">1–3 joints (Mild activity)</option>
                            <option value="4 to 10 tender joints (Moderate disease activity)">4–10 joints (Moderate activity)</option>
                            <option value=">10 tender joints (High disease activity / Polyarthritis)">>10 joints (High activity / Severe)</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Swollen Joint Count (SJC)</label>
                        <select class="zrx-exam-select" data-ortho-field="sjc">
                            <option value="0 (No active joint synovitis/swelling)">0 (No swollen joints - Remission)</option>
                            <option value="1 to 3 swollen joints (Mild synovitis)">1–3 joints (Mild synovitis)</option>
                            <option value="4 to 10 swollen joints (Moderate synovitis)">4–10 joints (Moderate synovitis)</option>
                            <option value=">10 swollen joints (High synovitis / Florid arthritis)">>10 joints (Florid synovitis)</option>
                        </select>
                    </div>
                </div>

                <!-- Stiffness & Spondyloarthritis Column -->
                <div class="zrx-exam-col">
                    <div class="zrx-exam-col-header">
                        <span class="zrx-exam-col-badge">STIFFNESS &amp; SPA</span>
                        <span>Inflammatory Signs &amp; SpA</span>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Morning Stiffness Duration</label>
                        <select class="zrx-exam-select" data-ortho-field="morning-stiffness">
                            <option value="None / Absent">No morning stiffness</option>
                            <option value="<30 minutes (Mechanical / Non-inflammatory / OA)">&lt;30 mins (Mechanical / OA)</option>
                            <option value="30 to 60 minutes (Moderate inflammatory stiffness)">30–60 mins (Inflammatory)</option>
                            <option value=">1 hour (Severe inflammatory stiffness / Active RA)">>1 hour (Severe / Active RA)</option>
                        </select>
                    </div>

                    <div class="zrx-exam-form-row">
                        <label class="zrx-exam-lbl">Axial Spondyloarthritis (SpA) Markers</label>
                        <select class="zrx-exam-select" data-ortho-field="spa-markers">
                            <option value="Modified Schober test normal (>5 cm), chest expansion normal">Schober &amp; chest expansion normal</option>
                            <option value="Modified Schober test restricted (<5 cm lumbar expansion)">Schober restricted (&lt;5 cm)</option>
                            <option value="Restricted chest expansion (<2.5 cm at 4th ICS)">Restricted chest expansion (&lt;2.5 cm)</option>
                            <option value="Occiput-to-wall distance increased (Kyphotic posture)">Occiput-to-wall distance increased</option>
                            <option value="Enthesitis (Achilles / Plantar fascial insertion tenderness)">Enthesitis (Achilles/Plantar)</option>
                            <option value="Dactylitis (Sausage digit) present">Dactylitis (Sausage digit)</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Presets -->
        <div class="zrx-exam-chips-bar">
            <span class="zrx-exam-chips-label">Quick Scenarios:</span>
            <button type="button" class="zrx-exam-chip" data-ortho-preset="knee-oa">Knee Osteoarthritis (Crepitus &amp; Varus)</button>
            <button type="button" class="zrx-exam-chip" data-ortho-preset="lumbar-pivd">Lumbar Radiculopathy (SLR +ve Sciatica)</button>
            <button type="button" class="zrx-exam-chip" data-ortho-preset="frozen-shoulder">Adhesive Capsulitis (Frozen Shoulder)</button>
            <button type="button" class="zrx-exam-chip" data-ortho-preset="ra-poly">Rheumatoid Arthritis (Symmetric Synovitis)</button>
            <button type="button" class="zrx-exam-chip" data-ortho-preset="cts">Carpal Tunnel Syndrome (Phalen +ve)</button>
            <button type="button" class="zrx-exam-chip" data-ortho-preset="normal-ortho">Normal MSK Exam</button>
        </div>

        <!-- Findings / Clinical Summary Textarea -->
        <div class="zrx-exam-findings-box">
            <label for="ortho-findings" class="zrx-exam-lbl">Orthopaedic &amp; Musculoskeletal Examination Summary</label>
            <textarea id="ortho-findings" class="zrx-exam-textarea" rows="3" placeholder="Musculoskeletal examination findings, joint stability, provocative tests..." autocomplete="off"></textarea>
        </div>
    </div>
</div>
