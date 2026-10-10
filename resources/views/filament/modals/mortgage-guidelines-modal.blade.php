@php
    $initialType = $collateralType ?? 'rem';
    if (in_array($initialType, ['chattel_mortgage', 'cm_certification', 'cm_amendment'])) {
        $initialTab = 'cm';
    } else {
        $initialTab = 'rem';
    }
@endphp

<style>
    .pmis-modal-slide {
        font-family: inherit;
        color: #1e293b;
    }
    .dark .pmis-modal-slide {
        color: #e2e8f0;
    }

    /* Tabs Bar Header */
    .pmis-tabs-bar {
        display: flex;
        gap: 8px;
        margin-bottom: 14px;
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 10px;
    }
    .dark .pmis-tabs-bar {
        border-bottom-color: #1e293b;
    }

    /* Tab Buttons */
    .pmis-tab-btn {
        padding: 9px 18px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s ease;
        border: 1px solid transparent;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    /* REM Active Tab */
    .pmis-tab-btn-active-rem {
        background-color: #fef3c7;
        border-color: #f59e0b;
        color: #92400e;
    }
    .dark .pmis-tab-btn-active-rem {
        background-color: rgba(245, 158, 11, 0.18);
        border-color: rgba(245, 158, 11, 0.45);
        color: #fbbf24;
    }

    /* CM Active Tab */
    .pmis-tab-btn-active-cm {
        background-color: #e0f2fe;
        border-color: #0284c7;
        color: #075985;
    }
    .dark .pmis-tab-btn-active-cm {
        background-color: rgba(6, 182, 212, 0.18);
        border-color: rgba(6, 182, 212, 0.45);
        color: #38bdf8;
    }

    /* Inactive Tab */
    .pmis-tab-btn-inactive {
        background-color: #f8fafc;
        border-color: #e2e8f0;
        color: #64748b;
    }
    .pmis-tab-btn-inactive:hover {
        background-color: #f1f5f9;
        color: #1e293b;
    }
    .dark .pmis-tab-btn-inactive {
        background-color: #0b1120;
        border-color: #1e293b;
        color: #94a3b8;
    }
    .dark .pmis-tab-btn-inactive:hover {
        background-color: #131d33;
        color: #f1f5f9;
    }

    /* Tab Badges */
    .pmis-tab-badge-rem {
        font-size: 10px;
        padding: 2px 7px;
        border-radius: 4px;
        background: #fde68a;
        color: #78350f;
    }
    .dark .pmis-tab-badge-rem {
        background: rgba(245, 158, 11, 0.25);
        color: #fef08a;
    }

    .pmis-tab-badge-cm {
        font-size: 10px;
        padding: 2px 7px;
        border-radius: 4px;
        background: #bae6fd;
        color: #0c4a6e;
    }
    .dark .pmis-tab-badge-cm {
        background: rgba(6, 182, 212, 0.25);
        color: #e0f2fe;
    }

    /* Reminder Banner */
    .pmis-reminder-banner {
        padding: 10px 14px;
        border-radius: 8px;
        background: #f0fdf4;
        border: 1px solid #86efac;
        font-size: 11.5px;
        color: #166534;
        line-height: 1.5;
    }
    .dark .pmis-reminder-banner {
        background: #0b1120;
        border-color: #1e293b;
        color: #94a3b8;
    }

    .pmis-reminder-title {
        color: #14532d;
        font-weight: 700;
    }
    .dark .pmis-reminder-title {
        color: #f8fafc;
    }

    /* Slide Grid */
    .pmis-slide-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
        gap: 11px;
    }

    /* Cards */
    .pmis-slide-card {
        background-color: #f0fdf4;
        border: 1.5px solid #86efac;
        border-radius: 9px;
        padding: 11px 13px;
        font-size: 12px;
        line-height: 1.45;
        transition: all 0.15s ease;
    }
    .pmis-slide-card:hover {
        border-color: #059669;
        background-color: #ecfdf5;
        box-shadow: 0 2px 6px rgba(16, 185, 129, 0.12);
    }
    .dark .pmis-slide-card {
        background-color: rgba(16, 185, 129, 0.08);
        border: 1px solid rgba(16, 185, 129, 0.4);
        box-shadow: none;
    }
    .dark .pmis-slide-card:hover {
        border-color: rgba(52, 211, 153, 0.7);
        background-color: rgba(16, 185, 129, 0.13);
        box-shadow: none;
    }

    /* Card Title */
    .pmis-card-title {
        color: #065f46;
        font-weight: 700;
        font-size: 12.5px;
        display: flex;
        align-items: baseline;
        gap: 6px;
    }
    .dark .pmis-card-title {
        color: #34d399;
    }

    .pmis-card-title-cm {
        color: #065f46;
    }
    .dark .pmis-card-title-cm {
        color: #34d399;
    }

    /* Card Number */
    .pmis-card-num {
        color: #047857;
        font-weight: 800;
        font-size: 12.5px;
        min-width: 18px;
    }
    .dark .pmis-card-num {
        color: #34d399;
    }

    /* Card Description */
    .pmis-card-desc {
        color: #334155;
        font-size: 11.5px;
        margin-top: 4px;
        line-height: 1.45;
    }
    .dark .pmis-card-desc {
        color: #cbd5e1;
        font-size: 11px;
    }

    /* Card Note */
    .pmis-card-note {
        color: #065f46;
        font-size: 10.5px;
        margin-top: 6px;
        padding: 5px 8px;
        border-radius: 5px;
        background: rgba(16, 185, 129, 0.1);
        border-left: 3px solid #10b981;
        font-style: italic;
    }
    .dark .pmis-card-note {
        color: #a7f3d0;
        background: transparent;
        border-left: none;
        padding: 5px 0 0 0;
        border-top: 1px solid rgba(16, 185, 129, 0.25);
        border-radius: 0;
    }
</style>

<div class="pmis-modal-slide" x-data="{ activeTab: '{{ $initialTab }}' }">
    
    <!-- Top Tabs Switcher -->
    <div class="pmis-tabs-bar">
        <button 
            type="button"
            @click="activeTab = 'rem'"
            :class="activeTab === 'rem' ? 'pmis-tab-btn pmis-tab-btn-active-rem' : 'pmis-tab-btn pmis-tab-btn-inactive'">
            <span>Real Estate Mortgage (REM)</span>
            <span class="pmis-tab-badge-rem">23 Requirements</span>
        </button>

        <button 
            type="button"
            @click="activeTab = 'cm'"
            :class="activeTab === 'cm' ? 'pmis-tab-btn pmis-tab-btn-active-cm' : 'pmis-tab-btn pmis-tab-btn-inactive'">
            <span>Chattel Mortgage (CM)</span>
            <span class="pmis-tab-badge-cm">25 Requirements</span>
        </button>
    </div>

    <!-- REAL ESTATE MORTGAGE (REM) -->
    <div x-show="activeTab === 'rem'" style="display: flex; flex-direction: column; gap: 12px;">
        <div class="pmis-reminder-banner">
            <strong class="pmis-reminder-title">Mortgage Specialist Reminder:</strong> Ensure all mandatory documents below are complete, authentic, and verified prior to submitting the application for credit evaluation and approval.
        </div>

        <div class="pmis-slide-grid">
            <!-- 1 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">1.</span>
                    <span>Latest Updated Tax Clearance</span>
                </div>
                <div class="pmis-card-desc">Must cover both land and improvements.</div>
            </div>

            <!-- 2 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">2.</span>
                    <span>Certified True Copy of Tax Declaration</span>
                </div>
                <div class="pmis-card-desc">Latest certified copy covering land and improvements from the Assessor's Office.</div>
            </div>

            <!-- 3 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">3.</span>
                    <span>Latest Official Tax Receipt</span>
                </div>
                <div class="pmis-card-desc">Current real property tax payment receipt covering land and improvements.</div>
            </div>

            <!-- 4 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">4.</span>
                    <span>DAR / DENR Certification or Affidavit of Non-Tenancy</span>
                </div>
                <div class="pmis-card-desc">Required for agricultural lots (other than financing) with an area of at least 10,000 sq. m.</div>
            </div>

            <!-- 5 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">5.</span>
                    <span>DAR Clearance</span>
                </div>
                <div class="pmis-card-desc">Required for all agricultural Real Estate Financing.</div>
            </div>

            <!-- 6 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">6.</span>
                    <span>Original Title (TCT / OCT / CCT)</span>
                </div>
                <div class="pmis-card-desc">Original Owner's Duplicate Copy of the title offered as mortgage security.</div>
            </div>

            <!-- 7 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">7.</span>
                    <span>Certified True Copy of Title (TCT / OCT / CCT)</span>
                </div>
                <div class="pmis-card-desc">Issued not later than sixty (60) days upon date of issuance.</div>
                <div class="pmis-card-note">Note: During credit evaluation, the Mortgage Document Specialist and Credit Investigator/Appraiser must verify the ROD copy of the title to ensure it is free from liens/encumbrances and the mortgage in favor of the Cooperative holds senior/first lien.</div>
            </div>

            <!-- 8 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">8.</span>
                    <span>Notarized Cancellation of Encumbrances</span>
                </div>
                <div class="pmis-card-desc">As needed (Previous mortgage, Section 4, Section 7, Lis Pendens, etc.).</div>
            </div>

            <!-- 9 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">9.</span>
                    <span>Collateral Property Pictures & Vicinity</span>
                </div>
                <div class="pmis-card-desc">Clear photos of the collateral property and surrounding vicinity.</div>
            </div>

            <!-- 10 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">10.</span>
                    <span>DENR Approved Plan / Technical Description</span>
                </div>
                <div class="pmis-card-desc">Approved plan, technical description, and vicinity map.</div>
            </div>

            <!-- 11 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">11.</span>
                    <span>PSA Birth / Marriage Certificate</span>
                </div>
                <div class="pmis-card-desc">For single: PSA Birth Certificate. For married: PSA Birth Certificate and Marriage Certificate for member-borrower and property owner/s.</div>
            </div>

            <!-- 12 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">12.</span>
                    <span>Notarized REM Contract</span>
                </div>
                <div class="pmis-card-desc">Duly executed Real Estate Mortgage contract.</div>
            </div>

            <!-- 13 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">13.</span>
                    <span>Notarized Promissory Note</span>
                </div>
                <div class="pmis-card-desc">Should be notarized at most one (1) week from loan release date.</div>
            </div>

            <!-- 14 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">14.</span>
                    <span>Notarized Special Power of Attorney (SPA)</span>
                </div>
                <div class="pmis-card-desc">Required if member-borrower is not the owner of the collateral property/ies.</div>
            </div>

            <!-- 15 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">15.</span>
                    <span>Consulate Office SPA</span>
                </div>
                <div class="pmis-card-desc">SPA from the Philippine Consulate Office where member/spouse is situated (if collateral owner or spouse is abroad).</div>
            </div>

            <!-- 16 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">16.</span>
                    <span>Notarized Affidavits</span>
                </div>
                <div class="pmis-card-desc">For minor discrepancies in names, marital status, technical details, etc.</div>
            </div>

            <!-- 17 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">17.</span>
                    <span>TIN of Borrower & Owner (and Spouses)</span>
                </div>
                <div class="pmis-card-desc">TIN of member-borrower & spouse, and property owner & spouse if member is not the collateral property/ies owner.</div>
            </div>

            <!-- 18 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">18.</span>
                    <span>Government Valid IDs with Specimen Signatures</span>
                </div>
                <div class="pmis-card-desc">Valid government IDs with three (3) specimen signatures each.</div>
            </div>

            <!-- 19 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">19.</span>
                    <span>Documentary Stamp Tax (DST 2000)</span>
                </div>
                <div class="pmis-card-desc">Documentary Stamp Tax BIR Form 2000 with original bank receipt (manual or online).</div>
            </div>

            <!-- 20 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">20.</span>
                    <span>Latest Appraisal Report</span>
                </div>
                <div class="pmis-card-desc">Latest property appraisal report conducted by Head Office or branch appraiser.</div>
            </div>

            <!-- 21 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">21.</span>
                    <span>Notarized Sworn Undertaking</span>
                </div>
                <div class="pmis-card-desc">Duly executed and notarized Sworn Undertaking.</div>
            </div>

            <!-- 22 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">22.</span>
                    <span>Transmittal with Credit Memo / Debit Transfer</span>
                </div>
                <div class="pmis-card-desc">Official transmittal document with credit memo or debit transfer receipt.</div>
            </div>

            <!-- 23 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">23.</span>
                    <span>Affidavit of Undertaking / Letter of Guarantee</span>
                </div>
                <div class="pmis-card-desc">Affidavit of Undertaking or Letter of Guarantee for financing loans.</div>
            </div>
        </div>
    </div>

    <!-- CHATTEL MORTGAGE (CM) -->
    <div x-show="activeTab === 'cm'" style="display: flex; flex-direction: column; gap: 12px;">
        <div class="pmis-reminder-banner">
            <strong class="pmis-reminder-title">Mortgage Specialist Reminder:</strong> Ensure all vehicle documents, stencil verification, and registrations are completely verified before endorsement.
        </div>

        <div class="pmis-slide-grid">
            <!-- 1 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">1.</span>
                    <span>Original Certificate of Registration of vehicle</span>
                </div>
                <div class="pmis-card-desc">Original Certificate of Registration (CR) of vehicle issued by LTO.</div>
            </div>

            <!-- 2 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">2.</span>
                    <span>Original Official Receipt (OR)</span>
                </div>
                <div class="pmis-card-desc">Original Official Receipt for current year’s renewal of registration.</div>
            </div>

            <!-- 3 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">3.</span>
                    <span>MVIR Form with Clear Stencil</span>
                </div>
                <div class="pmis-card-desc">Motor Vehicle Inspection Report with clear stencil of motor and chassis number (should strictly conform to the CR).</div>
            </div>

            <!-- 4 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">4.</span>
                    <span>Confirmation of CR/OR (CIR-91-137)</span>
                </div>
                <div class="pmis-card-desc">Required if issued by an LTO branch other than the branch where registration of mortgage will be processed.</div>
            </div>

            <!-- 5 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">5.</span>
                    <span>Macro-Etching Certificate & HPG Clearance</span>
                </div>
                <div class="pmis-card-desc">Required for surplus vehicles.</div>
            </div>

            <!-- 6 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">6.</span>
                    <span>Collateral Pictures (All Sides)</span>
                </div>
                <div class="pmis-card-desc">Clear photos of all sides reflecting the chassis and engine number where possible.</div>
            </div>

            <!-- 7 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">7.</span>
                    <span>PSA Birth / Marriage Certificate</span>
                </div>
                <div class="pmis-card-desc">For single or married member-borrower and property owner/s.</div>
            </div>

            <!-- 8 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">8.</span>
                    <span>Notarized Chattel Mortgage Contract</span>
                </div>
                <div class="pmis-card-desc">Duly executed and notarized Chattel Mortgage contract.</div>
            </div>

            <!-- 9 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">9.</span>
                    <span>Notarized Promissory Note</span>
                </div>
                <div class="pmis-card-desc">Should be notarized at most one (1) week from loan release date.</div>
            </div>

            <!-- 10 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">10.</span>
                    <span>Notarized Special Power of Attorney (SPA)</span>
                </div>
                <div class="pmis-card-desc">Required if member-borrower is not the owner of the collateral property/ies.</div>
            </div>

            <!-- 11 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">11.</span>
                    <span>Consulate Office SPA</span>
                </div>
                <div class="pmis-card-desc">SPA from the Philippine Consulate Office where member/spouse is situated (if collateral owner or spouse is abroad).</div>
            </div>

            <!-- 12 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">12.</span>
                    <span>Notarized Affidavits</span>
                </div>
                <div class="pmis-card-desc">For minor discrepancies in identity, address, or vehicle details.</div>
            </div>

            <!-- 13 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">13.</span>
                    <span>Notarized Affidavit of Marital Dispensation</span>
                </div>
                <div class="pmis-card-desc">For OFW spouse unable to personally sign the mortgage contract.</div>
            </div>

            <!-- 14 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">14.</span>
                    <span>TIN of Borrower & Owner (and Spouses)</span>
                </div>
                <div class="pmis-card-desc">TIN of member-borrower & spouse, and property owner & spouse if member is not the collateral property/ies owner.</div>
            </div>

            <!-- 15 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">15.</span>
                    <span>Government Valid IDs with Specimen Signatures</span>
                </div>
                <div class="pmis-card-desc">Valid government IDs with three (3) specimen signatures each.</div>
            </div>

            <!-- 16 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">16.</span>
                    <span>Documentary Stamp Tax (DST 2000)</span>
                </div>
                <div class="pmis-card-desc">BIR Form 2000 with original bank payment confirmation receipt (manual or online).</div>
            </div>

            <!-- 17 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">17.</span>
                    <span>Notarized Cancellation of Mortgage</span>
                </div>
                <div class="pmis-card-desc">If CR has prior encumbrance, attach copy of ROD released CM from other institution/s to assure its legitimacy.</div>
                <div class="pmis-card-note">Note: If Cancellation is already ROD released, attach the Original ROD receipt.</div>
            </div>

            <!-- 18 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">18.</span>
                    <span>Notarized Deed of Sale with Macro-Etching & HPG</span>
                </div>
                <div class="pmis-card-desc">Required if vehicle is subject for transfer of ownership, with Macro-etching & HPG clearance.</div>
            </div>

            <!-- 19 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">19.</span>
                    <span>Board Resolution or Secretary Certificate</span>
                </div>
                <div class="pmis-card-desc">Required if registered vehicle owner is a company or institution.</div>
            </div>

            <!-- 20 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">20.</span>
                    <span>Proof of Mortgagor's PPSR Registration</span>
                </div>
                <div class="pmis-card-desc">Proof of mortgagor's PPSR registration (Individual account).</div>
            </div>

            <!-- 21 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">21.</span>
                    <span>Latest Appraisal Report</span>
                </div>
                <div class="pmis-card-desc">Latest vehicle appraisal report conducted by Head Office or branch appraiser.</div>
            </div>

            <!-- 22 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">22.</span>
                    <span>Notarized Sworn Undertaking</span>
                </div>
                <div class="pmis-card-desc">Duly executed and notarized Sworn Undertaking.</div>
            </div>

            <!-- 23 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">23.</span>
                    <span>Transmittal with Credit Memo / Debit Transfer</span>
                </div>
                <div class="pmis-card-desc">Official transmittal document with credit memo or debit transfer receipt.</div>
            </div>

            <!-- 24 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">24.</span>
                    <span>Affidavit of Undertaking / Letter of Guarantee</span>
                </div>
                <div class="pmis-card-desc">Required for vehicle financing loans.</div>
            </div>

            <!-- 25 -->
            <div class="pmis-slide-card">
                <div class="pmis-card-title">
                    <span class="pmis-card-num">25.</span>
                    <span>LTO Verification (STRADCOM & LTMS)</span>
                </div>
                <div class="pmis-card-desc">Land Transportation Office (LTO) official verification records via STRADCOM Corporation and Land Transportation Management System (LTMS).</div>
            </div>
        </div>
    </div>

</div>