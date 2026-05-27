# -*- coding: utf-8 -*-
"""Build the Word document using the already-captured screenshots."""

import re
from pathlib import Path
from docx import Document
from docx.shared import Pt, RGBColor, Inches, Cm
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.table import WD_TABLE_ALIGNMENT, WD_ALIGN_VERTICAL
from docx.oxml.ns import qn
from docx.oxml import OxmlElement

SS_DIR = Path(r"d:\xampp\htdocs\invoiceiq\screenshots")
OUT    = r"d:\xampp\htdocs\invoiceiq\InvoiceIQ_User_Guide_v2.docx"

doc = Document()

# ── Page setup ────────────────────────────────────────────────────────────────
for section in doc.sections:
    section.top_margin    = Cm(2.0)
    section.bottom_margin = Cm(2.0)
    section.left_margin   = Cm(2.5)
    section.right_margin  = Cm(2.5)

style = doc.styles['Normal']
style.font.name = 'Calibri'
style.font.size = Pt(11)
style.font.color.rgb = RGBColor(0x11, 0x18, 0x27)

BRAND_BLUE = RGBColor(0x1A, 0x56, 0xDB)
BRAND_DARK = RGBColor(0x11, 0x18, 0x27)
BRAND_MUTED= RGBColor(0x6B, 0x72, 0x80)

# ── XML helpers ───────────────────────────────────────────────────────────────
def shade_para(para, hex_fill):
    pPr = para._p.get_or_add_pPr()
    shd = OxmlElement('w:shd')
    shd.set(qn('w:val'), 'clear')
    shd.set(qn('w:color'), 'auto')
    shd.set(qn('w:fill'), hex_fill)
    pPr.append(shd)

def shade_cell(cell, hex_fill):
    tcPr = cell._tc.get_or_add_tcPr()
    shd  = OxmlElement('w:shd')
    shd.set(qn('w:val'), 'clear')
    shd.set(qn('w:color'), 'auto')
    shd.set(qn('w:fill'), hex_fill)
    tcPr.append(shd)

def border_cell(cell, color='D1D5DB', sz='4'):
    tcPr = cell._tc.get_or_add_tcPr()
    tcB  = OxmlElement('w:tcBorders')
    for edge in ('top','left','bottom','right','insideH','insideV'):
        t = OxmlElement(f'w:{edge}')
        t.set(qn('w:val'), 'single')
        t.set(qn('w:sz'), sz)
        t.set(qn('w:space'), '0')
        t.set(qn('w:color'), color)
        tcB.append(t)
    tcPr.append(tcB)

def add_bottom_border(para, color='1A56DB', sz='6'):
    pPr  = para._p.get_or_add_pPr()
    pBdr = OxmlElement('w:pBdr')
    bot  = OxmlElement('w:bottom')
    bot.set(qn('w:val'), 'single')
    bot.set(qn('w:sz'), sz)
    bot.set(qn('w:space'), '1')
    bot.set(qn('w:color'), color)
    pBdr.append(bot)
    pPr.append(pBdr)

# ── Inline markdown renderer ──────────────────────────────────────────────────
def render_inline(para, text):
    for m in re.finditer(r'\*\*(.+?)\*\*|`(.+?)`|\*(.+?)\*|([^*`]+)', text):
        if m.group(1):
            r = para.add_run(m.group(1)); r.bold = True
        elif m.group(2):
            r = para.add_run(m.group(2))
            r.font.name = 'Courier New'; r.font.size = Pt(9.5)
            r.font.color.rgb = RGBColor(0xC7, 0x25, 0x4F)
        elif m.group(3):
            r = para.add_run(m.group(3)); r.italic = True
        elif m.group(4):
            para.add_run(m.group(4))

# ── Content helpers ───────────────────────────────────────────────────────────
def H2(text):
    p = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(18)
    p.paragraph_format.space_after  = Pt(4)
    r = p.add_run(text.upper())
    r.font.name = 'Calibri'; r.font.size = Pt(13)
    r.font.bold = True; r.font.color.rgb = BRAND_BLUE
    add_bottom_border(p)

def H3(text):
    p = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(12)
    p.paragraph_format.space_after  = Pt(2)
    r = p.add_run(text)
    r.font.name = 'Calibri'; r.font.size = Pt(11.5)
    r.font.bold = True; r.font.color.rgb = BRAND_DARK

def H4(text):
    p = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(8)
    p.paragraph_format.space_after  = Pt(2)
    shade_para(p, 'EFF6FF')
    r = p.add_run(text)
    r.font.name = 'Calibri'; r.font.size = Pt(11)
    r.font.bold = True; r.font.color.rgb = BRAND_BLUE

def body(text):
    p = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(2)
    p.paragraph_format.space_after  = Pt(6)
    render_inline(p, text)

def bullet(text, lvl=0):
    p = doc.add_paragraph(style='List Bullet')
    p.paragraph_format.left_indent  = Inches(0.25 + lvl*0.25)
    p.paragraph_format.space_before = Pt(1)
    p.paragraph_format.space_after  = Pt(1)
    render_inline(p, text)

def quote(text):
    p = doc.add_paragraph()
    shade_para(p, 'EFF6FF')
    p.paragraph_format.left_indent  = Inches(0.3)
    p.paragraph_format.space_before = Pt(6)
    p.paragraph_format.space_after  = Pt(6)
    r = p.add_run(text)
    r.font.name = 'Calibri'; r.font.size = Pt(10.5)
    r.font.italic = True; r.font.color.rgb = BRAND_BLUE

def divider():
    p = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(4)
    p.paragraph_format.space_after  = Pt(4)
    add_bottom_border(p, color='D1D5DB', sz='4')

def checkbox(text):
    p = doc.add_paragraph()
    p.paragraph_format.left_indent  = Inches(0.25)
    p.paragraph_format.space_before = Pt(1)
    p.paragraph_format.space_after  = Pt(1)
    r = p.add_run('☐  ')
    r.font.size = Pt(11); r.font.color.rgb = BRAND_MUTED
    render_inline(p, text)

def tbl(headers, rows, widths=None):
    t = doc.add_table(rows=1, cols=len(headers))
    t.alignment = WD_TABLE_ALIGNMENT.CENTER
    t.style = 'Table Grid'
    hr = t.rows[0]
    for i, h in enumerate(headers):
        c = hr.cells[i]
        shade_cell(c, '1A56DB'); border_cell(c, '1A56DB')
        c.vertical_alignment = WD_ALIGN_VERTICAL.CENTER
        pg = c.paragraphs[0]
        pg.paragraph_format.space_before = Pt(4)
        pg.paragraph_format.space_after  = Pt(4)
        rr = pg.add_run(h)
        rr.font.name = 'Calibri'; rr.font.size = Pt(10)
        rr.font.bold = True; rr.font.color.rgb = RGBColor(0xFF,0xFF,0xFF)
    for idx, rd in enumerate(rows):
        row = t.add_row()
        fill = 'EFF6FF' if idx%2==0 else 'FFFFFF'
        for j, ct in enumerate(rd):
            c = row.cells[j]
            shade_cell(c, fill); border_cell(c)
            c.vertical_alignment = WD_ALIGN_VERTICAL.CENTER
            pg = c.paragraphs[0]
            pg.paragraph_format.space_before = Pt(3)
            pg.paragraph_format.space_after  = Pt(3)
            render_inline(pg, ct)
            for rr in pg.runs:
                rr.font.name = 'Calibri'; rr.font.size = Pt(10)
    if widths:
        for i, w in enumerate(widths):
            for row in t.rows: row.cells[i].width = Inches(w)
    doc.add_paragraph().paragraph_format.space_after = Pt(4)

def screenshot(fname, caption=None, width=Inches(5.8)):
    path = SS_DIR / f"{fname}.png"
    if not path.exists():
        body(f"[Screenshot not available: {fname}]")
        return
    t = doc.add_table(rows=1, cols=1)
    t.alignment = WD_TABLE_ALIGNMENT.CENTER
    c = t.rows[0].cells[0]
    border_cell(c, 'CBD5E1', sz='6'); shade_cell(c, 'F8FAFC')
    c.vertical_alignment = WD_ALIGN_VERTICAL.CENTER
    pg = c.paragraphs[0]
    pg.alignment = WD_ALIGN_PARAGRAPH.CENTER
    pg.paragraph_format.space_before = Pt(4)
    pg.paragraph_format.space_after  = Pt(4)
    pg.add_run().add_picture(str(path), width=width)
    if caption:
        cp = doc.add_paragraph()
        cp.alignment = WD_ALIGN_PARAGRAPH.CENTER
        cp.paragraph_format.space_before = Pt(2)
        cp.paragraph_format.space_after  = Pt(10)
        cr = cp.add_run(f"Figure: {caption}")
        cr.font.size = Pt(9); cr.font.italic = True
        cr.font.color.rgb = BRAND_MUTED

def pb(): doc.add_page_break()

# ══════════════════════════════════════════════════════════════════════════════
# DOCUMENT
# ══════════════════════════════════════════════════════════════════════════════

# Cover
p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
shade_para(p, '1A56DB')
r = p.add_run('InvoiceIQ')
r.font.name='Calibri'; r.font.size=Pt(36); r.font.bold=True
r.font.color.rgb=RGBColor(0xFF,0xFF,0xFF)
p.paragraph_format.space_before=Pt(10); p.paragraph_format.space_after=Pt(0)

p2 = doc.add_paragraph()
p2.alignment = WD_ALIGN_PARAGRAPH.CENTER
shade_para(p2, '1A56DB')
r2 = p2.add_run('The Smarter Way to Run Your Business Billing')
r2.font.name='Calibri'; r2.font.size=Pt(14); r2.font.italic=True
r2.font.color.rgb=RGBColor(0xBF,0xDB,0xFF)
p2.paragraph_format.space_before=Pt(0); p2.paragraph_format.space_after=Pt(10)

p3 = doc.add_paragraph()
p3.alignment = WD_ALIGN_PARAGRAPH.CENTER
shade_para(p3, 'EFF6FF')
r3 = p3.add_run(
    'Built for Indian businesses. Designed to save your time, protect your money,\n'
    'and keep your GST filing stress-free — every single day.'
)
r3.font.name='Calibri'; r3.font.size=Pt(11); r3.font.italic=True
r3.font.color.rgb=BRAND_BLUE
p3.paragraph_format.space_before=Pt(8); p3.paragraph_format.space_after=Pt(14)

# ── Intro ─────────────────────────────────────────────────────────────────────
H2('You Deserve More Than a Spreadsheet')
body(
    'Picture this: a customer asks for an invoice. You open a spreadsheet, copy last month\'s '
    'format, manually calculate GST, type the customer\'s address again — only to realise '
    'you forgot to update the invoice number.'
)
quote('That ends today.')
body(
    'InvoiceIQ handles the math, remembers your customers, generates your GST reports, '
    'and delivers a professional invoice to your customer — in minutes, not an hour.'
)
H3('Who Is This For?')
for b in [
    'You bill customers and want to look professional every time',
    'You deal with GST and want filing day to stop being a nightmare',
    'You have outstanding payments and want to know exactly who owes you what',
    'You want one place for all your billing — not scattered files on your desktop',
]:
    bullet(b)

# ── Login ─────────────────────────────────────────────────────────────────────
divider()
H2('Getting Started — Logging In')
body(
    'Open InvoiceIQ in your browser. Enter your **email address** and **password**, '
    'then click **Login**. Check **Remember me** if you want to stay logged in on your device.'
)
screenshot('01_login', 'Login Screen')
body('Your name and role are always visible in the sidebar once you are in.')
tbl(
    ['Role', 'What They Can Do'],
    [
        ['Admin',  'Complete control — invoices, customers, items, reports, settings, user management'],
        ['Staff',  'Day-to-day billing — create and manage invoices, customers, and items'],
        ['Viewer', 'Read everything, change nothing — perfect for auditors or accountants'],
    ],
    [1.2, 5.0]
)

# ── Dashboard ─────────────────────────────────────────────────────────────────
pb()
H2('The Dashboard — Know Your Business Before Your Morning Chai')
body(
    'The moment you log in, InvoiceIQ shows you everything that matters about your '
    'business — no digging, no calculating, no waiting.'
)
screenshot('02_dashboard', 'Dashboard — Business Summary at a Glance')
H3('At a Single Glance')
tbl(
    ['What You See', 'Why It Matters'],
    [
        ['Total Invoiced',    'How much business you have generated — ever'],
        ['Total Paid',        'Real money collected — your actual revenue'],
        ['Total Outstanding', 'What is still owed to you — your follow-up list'],
        ['Total Overdue',     'Money past its due date — your urgent list'],
        ['Invoice Count',     'Your total volume of work in numbers'],
        ['Drafts',            'Invoices prepared but not yet sent'],
        ['Customers',         'How large your client base has grown'],
    ],
    [1.8, 4.4]
)
body(
    'Below the cards, a **6-month revenue chart** shows your monthly billing trend — '
    'spot growth, slow seasons, and plan your next push at a glance.'
)

# ── Invoices ──────────────────────────────────────────────────────────────────
pb()
H2('Invoices — Your Professionalism, Automated')
body(
    'An invoice is not just a document. It is how your customer sees your business. '
    'InvoiceIQ makes sure every invoice you send looks like it came from a company '
    'that means business.'
)

H3('Invoice List')
body(
    'Navigate to **Invoices** from the sidebar. Every invoice you have ever raised '
    'is listed here with status, amount, customer, and date. Use the search box and '
    'filters to find any invoice instantly.'
)
screenshot('03_invoice_list', 'Invoice List — All Invoices with Status and Filters')

H3('Creating a New Invoice')
body('Click **New Invoice** to open the invoice builder.')
screenshot('04_invoice_create_top', 'New Invoice Form — Customer and Invoice Details')

H4('Step 1 — Choose Your Customer')
body(
    'Start typing a customer name. InvoiceIQ suggests matches and fills in the '
    'address and GSTIN automatically — no copy-paste errors.'
)
H4('Step 2 — Invoice Identity')
for b in [
    '**Invoice Number** — Auto-generated (e.g., INV-2025-0001), unique, never repeated',
    '**Invoice Date** — Pre-filled with today',
    '**Due Date** — Once passed, the invoice automatically turns Overdue',
    '**Place of Supply** — Determines CGST+SGST (same state) or IGST (different state)',
]:
    bullet(b)

H4('Step 3 — Add Line Items')
body('Click **Add Item** for each product or service. All GST amounts calculate live as you type.')
screenshot('04b_invoice_lineitems', 'Invoice Line Items — Live GST Calculation')

H4('Step 4 — Totals, Discount & Notes')
body(
    'Apply an invoice-level discount (% or flat rupee), add payment notes and terms. '
    'Subtotal, CGST, SGST, IGST, round-off, and grand total all update instantly.'
)
screenshot('04c_invoice_totals', 'Invoice Totals — Auto-Calculated GST Breakup')

H3('Viewing an Invoice')
body(
    'Click any invoice to open the full detail view — line items, GST breakup, '
    'payment history, and action buttons: Edit, Send Email, Download PDF, Record Payment.'
)
screenshot('05_invoice_view', 'Invoice Detail View — Full Summary with Actions')

H3('Invoice Statuses')
tbl(
    ['Status', 'What It Means for You'],
    [
        ['Draft',     'In progress — customer hasn\'t seen it yet'],
        ['Sent',      'Delivered — awaiting payment'],
        ['Partial',   'Some payment received — follow up for the rest'],
        ['Paid',      'Collected in full — done!'],
        ['Overdue',   'Due date passed, still unpaid — urgent follow-up needed'],
        ['Cancelled', 'Voided — excluded from all totals and reports'],
    ],
    [1.2, 5.0]
)

H3('Recording Payments')
body(
    'Open any invoice and click **Record Payment**. Enter the amount, date, payment mode '
    '(Cash, UPI, NEFT, RTGS, Cheque, Card), and reference number. InvoiceIQ automatically '
    'updates the status — Partial or Paid — and keeps the full payment history.'
)

H3('PDF Download & Email')
body(
    'Click **Download PDF** for a professionally formatted A4 invoice with your logo. '
    'Click **Send Email** to deliver it directly to your customer — the PDF attaches '
    'automatically and the invoice status updates to Sent.'
)

# ── Customers ─────────────────────────────────────────────────────────────────
pb()
H2('Customers — Your Business Runs on Relationships')
body(
    'Save your clients once — every future invoice fills in their details automatically. '
    'No retyping addresses. No wrong GSTIN on a legal document.'
)
screenshot('06_customer_list', 'Customer List — Client Directory with Search')

H3('What You Store Per Customer')
for b in [
    'Name, email, phone',
    '**GSTIN** — Critical for B2B invoices and GST reports',
    'Billing and shipping addresses (separately if different)',
    'Default payment terms and credit limit',
    'Notes — anything important about this relationship',
]:
    bullet(b)

H3('Adding a New Customer')
screenshot('07_customer_create', 'New Customer Form — Complete Client Profile')

H3('Customer Insights')
body(
    'Open any customer to see their **total invoiced**, **total outstanding**, '
    'and complete invoice history — so every conversation with a client is backed by facts.'
)
quote(
    '“You have ₹45,000 outstanding across three invoices” '
    '— said with confidence, not guesswork.'
)

# ── Items ─────────────────────────────────────────────────────────────────────
pb()
H2('Items — Your Catalog, Your Speed')
body(
    'Build your product and service catalog once. When adding a line item on an invoice, '
    'start typing the name — InvoiceIQ fills in the HSN code, rate, and GST rate instantly.'
)
screenshot('08_items_list', 'Items Catalog — Products and Services with Default Rates')
quote(
    'What used to take 2 minutes per line item now takes 5 seconds. '
    'Multiply that by every invoice you raise this year.'
)
tbl(
    ['Field', 'Why It Matters'],
    [
        ['Name',             'What appears on the invoice'],
        ['HSN/SAC Code',     'Required for GST reports — save it once, never look it up again'],
        ['Type',             'Goods or Service — determines GST classification in reports'],
        ['Default Rate',     'Your standard price per unit'],
        ['Default GST Rate', '0%, 5%, 12%, 18%, or 28%'],
        ['Unit',             'Nos, KG, Hours, Qty, etc.'],
    ],
    [1.8, 4.4]
)

# ── GST Reports ───────────────────────────────────────────────────────────────
pb()
H2('GST Reports — Filing Day Is No Longer Stressful')
body(
    'InvoiceIQ generates every report your CA needs — GSTR-1 summary, B2B, B2C, '
    'HSN/SAC summary, and Tax Liability — in seconds. Export to **Excel** or **PDF**.'
)
screenshot('09_reports', 'GST Reports — All Filing Reports in One Place')

H3('Available Reports')
tbl(
    ['Report', 'What It Gives You'],
    [
        ['GSTR-1 Summary',  'Complete invoice list split into B2B and B2C — foundation for GSTR-1 filing'],
        ['B2B Report',      'One row per registered customer GSTIN — direct input for GSTR-1 B2B section'],
        ['B2C Report',      'Unregistered buyer sales grouped by state — for GSTR-1 B2C section'],
        ['HSN/SAC Summary', 'Sales by HSN/SAC code with quantity and GST — required for Schedule B'],
        ['Tax Liability',   'Tax owed by GST rate, broken down month by month — plan your payments'],
    ],
    [1.8, 4.4]
)
body(
    'Filter any report by **This month, Last month, This quarter, Last quarter, '
    'This year, Last year**, or a **custom date range**. Every report exports to '
    'a multi-sheet Excel workbook or a print-ready PDF.'
)

# ── Settings ──────────────────────────────────────────────────────────────────
pb()
H2('Settings — Make InvoiceIQ Truly Yours')
body(
    'Spend 10 minutes in Settings once, and every invoice you ever send carries your brand, '
    'your details, and your style. Admin access only.'
)
screenshot('10_settings_top', 'Settings — Company Information and Invoice Defaults')

H3('What You Configure')
for b in [
    '**Company Information** — Name, GSTIN, PAN, address, phone, email, logo',
    '**Bank Details** — Account number, IFSC, branch (shown on invoices for bank transfers)',
    '**Invoice Numbering** — Prefix + sequence, e.g., INV-2025-0001, INV-2025-0002',
    '**Invoice Defaults** — Payment terms and notes pre-filled on every new invoice',
    '**Email / SMTP** — Send invoices from your own business email address',
]:
    bullet(b)

H3('Email Configuration')
screenshot('10b_settings_email', 'Settings — SMTP Email Configuration with Test Button')
body(
    'Configure your SMTP host, port, credentials, and from-name once. Click '
    '**Test SMTP** to send a test email and confirm it works before your first invoice goes out.'
)

# ── Users ─────────────────────────────────────────────────────────────────────
divider()
H2('User Management — The Right People, The Right Access')
body('Bring your team in with exactly the access level they need. Admin access only.')
screenshot('11_users', 'User Management — Team Roles and Access Control')
tbl(
    ['Role', 'Perfect For'],
    [
        ['Admin',  'Business owner, finance head — full control'],
        ['Staff',  'Billing executive, sales team — invoice and client management'],
        ['Viewer', 'Accountant, auditor, investor — view everything, change nothing'],
    ],
    [1.2, 5.0]
)
body('A built-in protection prevents you from accidentally deleting your own account.')

# ── Quick-Start Checklist ─────────────────────────────────────────────────────
pb()
H2('Your Quick-Start Checklist')
body(
    'Before your first invoice, spend 10 minutes setting up InvoiceIQ. '
    'You will never have to do this again.'
)
for t in [
    'Settings → Add company name, GSTIN, logo, and bank details',
    'Settings → Set your invoice prefix and starting number',
    'Settings → Configure email/SMTP and click Test SMTP',
    'Items → Add your regularly sold products/services with HSN codes and GST rates',
    'Customers → Add your regular clients with their GSTIN',
]:
    checkbox(t)
quote('Once this is done, a complete GST-compliant invoice takes under two minutes.')

# ── Tips ──────────────────────────────────────────────────────────────────────
divider()
H2('Tips From Experienced Users')
for h, b in [
    ('Keep Your Items Catalog Complete',
     'Every HSN code you fill in now saves you from entering it manually 200 times this year — '
     'and makes your HSN Summary report accurate without extra work.'),
    ('Always Record Payments Promptly',
     'The Outstanding and Overdue cards on your dashboard are only as accurate as your payment '
     'records. Record the same day you receive — your dashboard always tells the truth.'),
    ('Save Every Customer — Even One-Time Clients',
     'You never know who comes back. A customer saved today is an invoice raised in 30 seconds '
     'next time they call.'),
    ('Use Draft Status Strategically',
     'Prepare invoices for month-end in advance as drafts. Review together, then send in one '
     'session. Organised, professional, efficient.'),
    ('Export Reports Before Your CA Asks',
     'At the end of every month, export your GSTR-1 Summary and Tax Liability report. '
     'When filing time comes, you are ready in five minutes.'),
]:
    H3(h); body(b)

# ── FAQ ───────────────────────────────────────────────────────────────────────
pb()
H2('Common Questions, Straight Answers')
for q, a in [
    ('I sell to customers in other states. How does InvoiceIQ handle that?',
     'Automatically. When your state and the customer\'s state differ, InvoiceIQ charges IGST '
     'instead of CGST+SGST. It detects this from the Place of Supply on each invoice — '
     'you configure nothing.'),
    ('A customer paid in two instalments. What do I do?',
     'Record each payment separately using Record Payment. InvoiceIQ shows Partial after the '
     'first and Paid after the final. Full history, full audit trail.'),
    ('I sent an invoice with a wrong amount. Can I fix it?',
     'Yes. Open the invoice, click Edit, fix it, and save. If you already emailed it, '
     'send a revised copy with a note.'),
    ('I want to cancel an invoice raised by mistake.',
     'Change the status to Cancelled. It stays in the system for records but is excluded '
     'from all reports and totals.'),
    ('My accountant asks for a GST report every month. How fast can I send it?',
     'Under a minute. Open GST Reports, select the period, click Export Excel. A multi-sheet '
     'workbook — GSTR-1, B2B, B2C, HSN, Tax Liability — is ready to email.'),
    ('What is Place of Supply and why does it matter?',
     'It is the state where goods are delivered or services are consumed. GST law requires it '
     'on every invoice. InvoiceIQ uses it to automatically apply the correct tax — '
     'CGST+SGST or IGST.'),
]:
    H4('Q: ' + q); body(a)

# ── Closing ───────────────────────────────────────────────────────────────────
divider()
cp = doc.add_paragraph()
cp.alignment = WD_ALIGN_PARAGRAPH.CENTER
shade_para(cp, '1A56DB')
cp.paragraph_format.space_before = Pt(12)
cp.paragraph_format.space_after  = Pt(12)
r = cp.add_run(
    'InvoiceIQ — Built for Indian businesses. Trusted by people who mean business.'
)
r.font.name='Calibri'; r.font.size=Pt(12)
r.font.bold=True; r.font.italic=True
r.font.color.rgb=RGBColor(0xFF,0xFF,0xFF)

doc.save(OUT)
print(f"Saved: {OUT}")
