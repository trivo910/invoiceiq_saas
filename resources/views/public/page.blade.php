@extends('public.layout')
@section('title', ucfirst($page).' | InvoiceIQ')
@section('content')
<article class="card">
    <div class="card-body">
        <p><span class="badge">SAMPLE DRAFT · NOT APPROVED · DO NOT RELY ON THIS TEXT</span></p>
        <h1>{{ $page === 'terms' ? 'Terms of service' : 'Privacy notice' }}</h1>
        <p><strong>Sample revision date:</strong> 29 October 2026</p>
        <div class="card">
            <div class="card-body">
                <h2>Fictional sample operator details</h2>
                <p><strong>Sample name:</strong> InvoiceIQ Sample Operator (fictional placeholder; not a real company or legal entity)</p>
                <p><strong>Sample contact:</strong> <a href="mailto:legal@example.invalid">legal@example.invalid</a> (reserved, non-deliverable example address)</p>
                <p>Replace both values with verified operator details before publication. The sample does not identify who owns, sells, or operates the live service.</p>
            </div>
        </div>

        @if($page === 'terms')
            <h2>1. What the service does</h2>
            <p>InvoiceIQ is a software service for creating and managing business workspaces, customer and item records, invoices, payment records, exports, and related billing workflows. The production operator, service address, and binding contracting party have not been identified in this sample.</p>

            <h2>2. Account and workspace responsibilities</h2>
            <p>Illustrative assumption: an account holder must provide accurate registration details, protect login credentials, keep authorized users and workspace roles current, and promptly report suspected account misuse. The workspace owner is responsible for inviting people who are permitted to access its records.</p>

            <h2>3. Business records, tax and invoices</h2>
            <p>You control the business information and customer details entered into your workspace and must have authority to use them. You are responsible for checking invoice recipients, dates, descriptions, GSTIN/PAN/state details, tax treatment, totals, and issued documents. InvoiceIQ is not a tax, accounting, or legal adviser and does not replace review by a qualified professional. This sample does not promise that an invoice or tax calculation is correct for your transaction.</p>

            <h2>4. Acceptable use</h2>
            <p>Illustrative assumption: use the service only for lawful business activity. Do not attempt to access another workspace, interfere with the service, upload malicious material, misuse shared invoice links, or use the service to violate another person's rights or applicable law. The final operator must tailor these rules to the actual product and applicable law.</p>

            <h2>5. Plans, trial and payment</h2>
            <p>Prices, taxes, plan limits, renewal terms, payment provider responsibilities, refunds, cancellation timing, failed-payment handling, and service access rules have not been approved. The application currently contains configurable trials and subscription workflows; their presence does not make the values or policies in this sample a customer offer.</p>
            <p><strong>Example policy for review only:</strong> a paid plan could renew for the selected billing interval until cancelled, with cancellation stopping a future renewal and access continuing through a paid period. Do not publish this example until it matches the actual checkout, provider configuration, refund rules, and approved commercial decisions.</p>

            <h2>6. User content and service rights</h2>
            <p>Illustrative assumption: you retain rights to the business records you submit and grant the operator only the permissions needed to host, process, display, export, and protect them while providing the service. The operator's software, name, and design rights must be confirmed by the actual owner before this clause is used.</p>

            <h2>7. Availability, suspension and termination</h2>
            <p>No availability, recovery-time, backup, or support response commitment is made by this sample. The current application can suspend or soft-delete a workspace and retains deleted workspace records for platform recovery; it does not currently promise a permanent-erasure deadline. Any final suspension, termination, data return, and deletion terms must match implemented operations and an approved retention policy.</p>

            <h2>8. Disclaimers and liability</h2>
            <p>Any warranty disclaimer, liability allocation, indemnity, or remedy limitation must be drafted for the actual operator and reviewed for enforceability. Nothing in this draft excludes a right or liability that cannot legally be excluded or limited. No monetary liability cap is proposed here.</p>

            <h2>9. Governing law and contact</h2>
            <p>India is used only as a sample jurisdiction because the product is designed around Indian GST invoicing. The state, courts, contracting entity, and dispute process are placeholders and must be selected by the real operator after legal review. Sample contact: <a href="mailto:legal@example.invalid">legal@example.invalid</a>.</p>
        @else
            <h2>1. Scope and roles</h2>
            <p>This sample describes information the current InvoiceIQ application may handle. Depending on the feature, the workspace operator may decide why customer and invoice information is entered, while the service operator processes workspace information to run accounts and features. The actual legal roles, notices, and lawful grounds must be determined for the final service and each use.</p>

            <h2>2. Information categories</h2>
            <ul>
                <li><strong>Account:</strong> user name, email address, password hash, verification and workspace membership/role information.</li>
                <li><strong>Workspace profile:</strong> business name, address, GSTIN/PAN/state details, logo, bank/payment instructions, invoice settings and preferences.</li>
                <li><strong>Records entered by a workspace:</strong> customer contact and billing details, GST identifiers, catalog items, invoice content, payment entries, and related files.</li>
                <li><strong>Service and billing activity:</strong> plan/subscription state, provider identifiers and event metadata, audit actions and changed field names, queued email/export state, and support/impersonation audit information.</li>
                <li><strong>Technical information:</strong> session and security information and request metadata where captured in application or server logs. Exact log fields and retention must be verified on the deployed host.</li>
            </ul>
            <p>The application is not intended to store card numbers or card security codes in its own workspace tables. The final operator must verify the payment provider's hosted checkout and data flow before relying on this statement.</p>

            <h2>3. Why information is used</h2>
            <p>Illustrative purposes include creating accounts and workspaces, authenticating users, creating and exporting requested business records, delivering emails and shared invoice documents, processing subscription events when billing is enabled, enforcing roles and plan limits, preventing misuse, and supporting service reliability. The legal basis, notice/consent flow, and statutory obligations for each purpose need counsel review.</p>

            <h2>4. Sharing and service providers</h2>
            <p>Information may be available to authorized workspace members and platform support staff for their permitted tasks. The application can be configured to use a hosting provider, SMTP/email provider, and Razorpay for subscription billing. The production VPS host and email provider have not been selected here; Razorpay processing is not verified as enabled. Before launch, publish the actual provider names, roles, processing locations, transfer details, and applicable contractual safeguards. This sample makes no claim that data is never transferred internationally.</p>

            <h2>5. Retention and deletion</h2>
            <p>Records are retained while needed to operate the workspace and meet approved business/legal requirements. Current implementation facts: owner-requested workspace deletion is a soft deletion; records remain available for platform recovery with no automatic permanent purge. Audit history is append-only at the application layer. Invoice share links have a configured expiry, and generated workspace ZIP exports expire after 24 hours. A final retention schedule, backup expiry, log retention, and permanent deletion process are not yet approved.</p>

            <h2>6. Security</h2>
            <p>The application implements tenant scoping, role checks, security headers, private-file routes, and encryption for selected stored secrets and email payloads. These controls do not mean every database field or file is encrypted at rest, and no system can promise absolute security. Production infrastructure, access monitoring, incident response, and backup controls must be confirmed by the operator.</p>

            <h2>7. Requests and contact</h2>
            <p>Workspace users can correct some profile and business settings in the application; the workspace owner can request a data export. This is not a complete personal-data rights process. Request verification, correction/deletion handling, complaints, response timelines, and a grievance contact must be finalized by the actual operator. Sample contact: <a href="mailto:legal@example.invalid">legal@example.invalid</a> (non-deliverable placeholder; requests sent here will not reach the operator).</p>

            <h2>8. Children and changes</h2>
            <p>The intended age/eligibility policy and treatment of information about minors have not been approved. The final notice must explain how material changes are communicated and the effective date of the approved version.</p>
        @endif

        <div class="card">
            <div class="card-body">
                <h2>Official reference materials for legal review</h2>
                <p>These links are source materials, not a conclusion that a particular provision applies to this sample operator. Check amendments, commencement notifications, rules, and applicability with qualified Indian counsel before publishing.</p>
                <ul>
                    <li><a href="https://egazette.gov.in/WriteReadData/2023/248045.pdf" target="_blank" rel="noopener noreferrer">Digital Personal Data Protection Act, 2023 (Gazette of India)</a></li>
                    <li><a href="https://egazette.gov.in/WriteReadData/2025/267647.pdf" target="_blank" rel="noopener noreferrer">DPDP Act commencement notification, 13 November 2025 (phased commencement)</a></li>
                    <li><a href="https://www.meity.gov.in/static/uploads/2025/11/53450e6e5dc0bfa85ebd78686cadad39.pdf" target="_blank" rel="noopener noreferrer">Digital Personal Data Protection Rules, 2025 (MeitY Gazette copy)</a></li>
                    <li><a href="https://www.indiacode.nic.in/bitstream/123456789/13116/1/it_act_2000_updated.pdf" target="_blank" rel="noopener noreferrer">Information Technology Act, 2000 (India Code)</a></li>
                    <li><a href="https://upload.indiacode.nic.in/showfile?actid=AC_CEN_45_76_00001_200021_1517807324077&amp;filename=GSR313E_10511%281%29_0.pdf&amp;type=rule" target="_blank" rel="noopener noreferrer">Information Technology (Reasonable Security Practices and Procedures and Sensitive Personal Data or Information) Rules, 2011 (India Code)</a></li>
                </ul>
                <p><strong>Commencement note:</strong> the 2025 Gazette notification phases provisions of the DPDP Act and Rules. This draft deliberately avoids asserting which duties are currently in force for the eventual operator; verify the latest official Gazette and legal advice before launch.</p>
            </div>
        </div>
        <p class="muted"><strong>Review note:</strong> fictional sample details and policy assumptions only. Not approved legal advice, a contract, a privacy notice, or a statement of the live operator's actual practices. Keep `LAUNCH_LEGAL_APPROVED=false` until the real entity, contacts, operational facts, and final reviewed text replace this sample.</p>
    </div>
</article>
@endsection
