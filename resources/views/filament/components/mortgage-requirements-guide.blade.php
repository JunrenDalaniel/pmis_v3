@php
    $type = $collateralType ?? null;
    $isRem = in_array($type, ['real_estate_mortgage', 'rem_certification', 'rem_amendment']);
    $isCm = in_array($type, ['chattel_mortgage', 'cm_certification', 'cm_amendment']);
@endphp

<style>
    .pmis-guide-wrapper {
        border-radius: 12px;
        overflow: hidden;
        margin: 6px 0 14px 0;
        font-family: inherit;
        border: 1px solid #334155;
        background-color: #0d1527;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2);
    }
    .pmis-guide-header-rem {
        padding: 10px 16px;
        background: linear-gradient(90deg, rgba(245, 158, 11, 0.2) 0%, rgba(245, 158, 11, 0.05) 100%);
        border-bottom: 1px solid rgba(245, 158, 11, 0.3);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .pmis-guide-header-cm {
        padding: 10px 16px;
        background: linear-gradient(90deg, rgba(6, 182, 212, 0.2) 0%, rgba(6, 182, 212, 0.05) 100%);
        border-bottom: 1px solid rgba(6, 182, 212, 0.3);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .pmis-guide-body {
        padding: 14px 16px;
    }
    .pmis-guide-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 10px;
    }
    .pmis-req-card {
        border-radius: 8px;
        padding: 9px 12px;
        font-size: 11.5px;
        line-height: 1.45;
        border: 1px solid #1e293b;
        background-color: #111a2e;
        color: #cbd5e1;
    }
    .pmis-req-card-rem-alert {
        border: 1px solid rgba(245, 158, 11, 0.4);
        background-color: rgba(245, 158, 11, 0.08);
    }
    .pmis-req-card-cm-alert {
        border: 1px solid rgba(6, 182, 212, 0.4);
        background-color: rgba(6, 182, 212, 0.08);
    }
    .pmis-req-card-danger {
        border: 1px solid rgba(244, 63, 94, 0.4);
        background-color: rgba(244, 63, 94, 0.08);
    }
    .pmis-req-card-success {
        border: 1px solid rgba(16, 185, 129, 0.4);
        background-color: rgba(16, 185, 129, 0.08);
    }
    .pmis-title-rem { color: #fbbf24; font-weight: 700; }
    .pmis-title-cm { color: #38bdf8; font-weight: 700; }
    .pmis-title-rose { color: #fb7185; font-weight: 700; }
    .pmis-title-emerald { color: #34d399; font-weight: 700; }
    .pmis-badge {
        font-size: 10px;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 6px;
        letter-spacing: 0.025em;
    }
    .pmis-badge-amber {
        background-color: rgba(245, 158, 11, 0.2);
        color: #fcd34d;
        border: 1px solid rgba(245, 158, 11, 0.35);
    }
    .pmis-badge-cyan {
        background-color: rgba(6, 182, 212, 0.2);
        color: #7dd3fc;
        border: 1px solid rgba(6, 182, 212, 0.35);
    }
    .pmis-note-text {
        font-size: 10.5px;
        color: #94a3b8;
        margin-top: 3px;
        display: block;
    }
</style>

<div class="pmis-guide-wrapper">
    @if ($isRem)
        {{-- ================= REAL ESTATE MORTGAGE (REM) ================= --}}
        <div class="pmis-guide-header-rem">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 18px;">🏠</span>
                <div>
                    <div style="font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #fbbf24;">
                        Real Estate Mortgage (REM) Mandatory Documentary Checklist
                    </div>
                    <div style="font-size: 11px; color: #94a3b8;">
                        Siguraduhing kumpleto at nasuri ang mga sumusunod bago ipasa sa Head Office:
                    </div>
                </div>
            </div>
            <span class="pmis-badge pmis-badge-amber">18 Mandatory Items</span>
        </div>

        <div class="pmis-guide-body">
            <div class="pmis-guide-grid">
                <div class="pmis-req-card">
                    <span class="pmis-title-rem">1. Latest Updated Tax Clearance</span>
                    <span class="pmis-note-text">Dapat sakop pareho ang lupa (land) at anumang improvements / estruktura.</span>
                </div>

                <div class="pmis-req-card">
                    <span class="pmis-title-rem">2. Latest Certified True Copy of Tax Declaration</span>
                    <span class="pmis-note-text">Mula sa Assessor's Office para sa land at improvements.</span>
                </div>

                <div class="pmis-req-card">
                    <span class="pmis-title-rem">3. Latest Official Tax Receipt</span>
                    <span class="pmis-note-text">Resibo ng pinakahuling bayad sa amilyar (land & improvements).</span>
                </div>

                <div class="pmis-req-card pmis-req-card-rem-alert">
                    <span class="pmis-title-rem">⚠️ 4. DAR/DENR Clearance & Non-Tenancy</span>
                    <span class="pmis-note-text" style="color: #e2e8f0;">
                        Para sa <strong>agricultural lots</strong> (other than financing) na may laking <strong>&ge; 10,000 sq. m.</strong>, at DAR Clearance para sa lahat ng agricultural Real Estate Financing.
                    </span>
                </div>

                <div class="pmis-req-card pmis-req-card-success">
                    <span class="pmis-title-emerald">✓ 5. Original Land Title (TCT / OCT / CCT)</span>
                    <span class="pmis-note-text">Orihinal na kopya ng titulo ng lupang isinasangla sa Cooperative.</span>
                </div>

                <div class="pmis-req-card pmis-req-card-danger">
                    <span class="pmis-title-rose">⚠️ 6. CTC of Title (&le; 30 Days Upon Issuance)</span>
                    <span class="pmis-note-text" style="color: #fecdd3;">
                        <strong>Paalala:</strong> Sa credit evaluation, dapat i-check ng CI/Appraiser ang ROD copy upang matiyak na free from liens at senior lien ang Cooperative.
                    </span>
                </div>

                <div class="pmis-req-card">
                    <span class="pmis-title-rem">7. Notarized Cancellation of Encumbrances</span>
                    <span class="pmis-note-text">Kung may dating mortgage, Sec. 4, Sec. 7, Lis Pendens, atbp.</span>
                </div>

                <div class="pmis-req-card">
                    <span class="pmis-title-rem">8. Pictures, Vicinity Map & DENR Approved Plan</span>
                    <span class="pmis-note-text">Larawan ng property at DENR technical description / vicinity map.</span>
                </div>

                <div class="pmis-req-card">
                    <span class="pmis-title-rem">9. PSA Birth / Marriage Certificates</span>
                    <span class="pmis-note-text">Para sa single/married borrower at mga may-ari ng collateral property.</span>
                </div>

                <div class="pmis-req-card pmis-req-card-rem-alert">
                    <span class="pmis-title-rem">⚠️ 10. Notarized REM & Promissory Note</span>
                    <span class="pmis-note-text" style="color: #fef08a;">
                        <strong>Batas:</strong> Dapat <em>magkapareho ang Notary Date</em> ng Promissory Note at REM execution date.
                    </span>
                </div>

                <div class="pmis-req-card">
                    <span class="pmis-title-rem">11. Notarized SPA & Affidavits</span>
                    <span class="pmis-note-text">SPA kung hindi borrower ang may-ari ng lupa; Affidavits para sa minor discrepancies.</span>
                </div>

                <div class="pmis-req-card">
                    <span class="pmis-title-rem">12. TIN, Valid IDs & DST 2000</span>
                    <span class="pmis-note-text">TIN ng borrower at may-ari, Gov't IDs w/ 3 specimen signatures, at DST 2000 bank receipt.</span>
                </div>
            </div>
        </div>

    @elseif ($isCm)
        {{-- ================= CHATTEL MORTGAGE (CM) ================= --}}
        <div class="pmis-guide-header-cm">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 18px;">🚗</span>
                <div>
                    <div style="font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #38bdf8;">
                        Chattel Mortgage (CM) Mandatory Documentary Checklist
                    </div>
                    <div style="font-size: 11px; color: #94a3b8;">
                        Siguraduhing kumpleto at nasuri ang mga dokumento ng sasakyan bago ipasa sa Head Office:
                    </div>
                </div>
            </div>
            <span class="pmis-badge pmis-badge-cyan">16 Mandatory Items</span>
        </div>

        <div class="pmis-guide-body">
            <div class="pmis-guide-grid">
                <div class="pmis-req-card pmis-req-card-cm-alert">
                    <span class="pmis-title-cm">1. Original CR & Current Year Official Receipt (OR)</span>
                    <span class="pmis-note-text">Orihinal na Certificate of Registration at pinakabagong OR ng renewal para sa kasalukuyang taon.</span>
                </div>

                <div class="pmis-req-card pmis-req-card-danger">
                    <span class="pmis-title-rose">⚠️ 2. MVIR Form w/ Stencil of Motor & Chassis</span>
                    <span class="pmis-note-text" style="color: #fecdd3;">
                        <strong>Kritikal:</strong> Dapat malinaw ang stencil at <em>100% tumutugma</em> sa nakasaad sa Certificate of Registration (CR).
                    </span>
                </div>

                <div class="pmis-req-card">
                    <span class="pmis-title-cm">3. Collateral Pictures (All 4 Sides)</span>
                    <span class="pmis-note-text">Malinaw na larawan ng sasakyan sa lahat ng anggulo at nagpapakita ng engine at chassis number kung maari.</span>
                </div>

                <div class="pmis-req-card">
                    <span class="pmis-title-cm">4. PSA Birth / Marriage Certificates</span>
                    <span class="pmis-note-text">Para sa single/married borrower at sa rehistradong may-ari ng sasakyan.</span>
                </div>

                <div class="pmis-req-card pmis-req-card-cm-alert">
                    <span class="pmis-title-cm">⚠️ 5. Notarized CM & Promissory Note</span>
                    <span class="pmis-note-text" style="color: #e0f2fe;">
                        Dapat <em>magkapareho ang Notary Date</em> ng Promissory Note at Chattel Mortgage execution date.
                    </span>
                </div>

                <div class="pmis-req-card pmis-req-card-rem-alert">
                    <span class="pmis-title-rem">⚠️ 6. Affidavit of Marital Dispensation (OFW)</span>
                    <span class="pmis-note-text" style="color: #fef08a;">
                        Notarized Affidavit kung ang asawa ay nasa ibang bansa (OFW) at hindi personal na makakapirma.
                    </span>
                </div>

                <div class="pmis-req-card">
                    <span class="pmis-title-cm">7. Cancellation of Previous Mortgage</span>
                    <span class="pmis-note-text">Kung may encumbrance sa CR: Notarized Cancellation kalakip ang ROD released copy & resibo.</span>
                </div>

                <div class="pmis-req-card pmis-req-card-danger">
                    <span class="pmis-title-rose">⚠️ 8. Deed of Sale w/ HPG / ANCAR Clearance</span>
                    <span class="pmis-note-text" style="color: #fecdd3;">
                        Kinakailangan kung ang sasakyan ay subject for transfer of ownership sa borrower.
                    </span>
                </div>

                <div class="pmis-req-card">
                    <span class="pmis-title-cm">9. Corporate Vehicle: Board Resolution</span>
                    <span class="pmis-note-text">Board Resolution o Secretary's Certificate kung rehistrado sa kumpanya/institusyon.</span>
                </div>

                <div class="pmis-req-card">
                    <span class="pmis-title-cm">10. TIN, Valid IDs & DST 2000</span>
                    <span class="pmis-note-text">TIN ng borrower at may-ari, Valid IDs w/ 3 specimen signatures, at DST 2000 bank receipt.</span>
                </div>
            </div>
        </div>

    @else
        {{-- ================= UNSELECTED STATE ================= --}}
        <div style="padding: 16px; display: flex; align-items: center; gap: 12px; background-color: #0f172a;">
            <span style="font-size: 20px;">ℹ️</span>
            <div>
                <div style="font-size: 12px; font-weight: 700; color: #f8fafc;">Gabay sa Pagsumite ng Mortgage Documents</div>
                <div style="font-size: 11px; color: #94a3b8;">
                    Pumili ng <strong>Mortgage Security Type</strong> sa itaas (Real Estate Mortgage o Chattel Mortgage) upang kusa at awtomatikong lumabas ang kumpletong checklist ng mga mandatory requirements.
                </div>
            </div>
        </div>
    @endif
</div>