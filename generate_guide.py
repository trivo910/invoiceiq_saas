from docx import Document
from docx.shared import Pt, RGBColor, Inches, Cm
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.table import WD_TABLE_ALIGNMENT, WD_ALIGN_VERTICAL
from docx.oxml.ns import qn
from docx.oxml import OxmlElement
import re

doc = Document()

# ── Page margins ──────────────────────────────────────────────────────────────
for section in doc.sections:
    section.top_margin    = Cm(2.0)
    section.bottom_margin = Cm(2.0)
    section.left_margin   = Cm(2.5)
    section.right_margin  = Cm(2.5)

# ── Colour palette ────────────────────────────────────────────────────────────
BRAND_BLUE   = RGBColor(0x1A, 0x56, 0xDB)   # headings
BRAND_DARK   = RGBColor(0x11, 0x18, 0x27)   # body
BRAND_ACCENT = RGBColor(0x0E, 0x9F, 0x6E)   # tick / special
BRAND_MUTED  = RGBColor(0x6B, 0x72, 0x80)   # caption / muted
TABLE_HEADER = RGBColor(0x1A, 0x56, 0xDB)   # table header bg
TABLE_ALT    = RGBColor(0xEF, 0xF6, 0xFF)   # alternate row

# ── Base body style ───────────────────────────────────────────────────────────
style = doc.styles['Normal']
style.font.name = 'Calibri'
style.font.size = Pt(11)
style.font.color.rgb = BRAND_DARK

# ── Helper: set paragraph shading ─────────────────────────────────────────────
def shade_paragraph(para, hex_fill):
    pPr = para._p.get_or_add_pPr()
    shd = OxmlElement('w:shd')
    shd.set(qn('w:val'), 'clear')
    shd.set(qn('w:color'), 'auto')
    shd.set(qn('w:fill'), hex_fill)
    pPr.append(shd)

# ── Helper: shade a table cell ────────────────────────────────────────────────
def shade_cell(cell, hex_fill):
    tc   = cell._tc
    tcPr = tc.get_or_add_tcPr()
    shd  = OxmlElement('w:shd')
    shd.set(qn('w:val'),   'clear')
    shd.set(qn('w:color'), 'auto')
    shd.set(qn('w:fill'),  hex_fill)
    tcPr.append(shd)

# ── Helper: set cell borders ──────────────────────────────────────────────────
def set_cell_border(cell, **kwargs):
    tc   = cell._tc
    tcPr = tc.get_or_add_tcPr()
    tcBorders = OxmlElement('w:tcBorders')
    for edge in ('top','left','bottom','right','insideH','insideV'):
        tag = OxmlElement(f'w:{edge}')
        tag.set(qn('w:val'),   kwargs.get('val',   'single'))
        tag.set(qn('w:sz'),    kwargs.get('sz',    '4'))
        tag.set(qn('w:space'), '0')
        tag.set(qn('w:color'), kwargs.get('color', 'D1D5DB'))
        tcBorders.append(tag)
    tcPr.append(tcBorders)

# ── Helper: add inline runs with bold/italic/code ─────────────────────────────
def add_inline(para, text, bold=False, italic=False, color=None, size=None, code=False):
    run = para.add_run(text)
    run.bold   = bold
    run.italic = italic
    if code:
        run.font.name = 'Courier New'
        run.font.size = Pt(9.5)
        run.font.color.rgb = RGBColor(0xC7, 0x25, 0x4F)
    if color:
        run.font.color.rgb = color
    if size:
        run.font.size = Pt(size)
    return run

# ── Helper: parse inline markdown (bold/italic/code) into runs ────────────────
def render_inline(para, text):
    pattern = r'(\*\*(.+?)\*\*|`(.+?)`|\*(.+?)\*)'
    last = 0
    for m in re.finditer(pattern, text):
        if m.start() > last:
            para.add_run(text[last:m.start()])
        if m.group(2):   # **bold**
            add_inline(para, m.group(2), bold=True)
        elif m.group(3): # `code`
            add_inline(para, m.group(3), code=True)
        elif m.group(4): # *italic*
            add_inline(para, m.group(4), italic=True)
        last = m.end()
    if last < len(text):
        para.add_run(text[last:])

# ── Title block ───────────────────────────────────────────────────────────────
def add_title_block():
    # big brand title
    title_p = doc.add_paragraph()
    title_p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    shade_paragraph(title_p, '1A56DB')
    run = title_p.add_run('InvoiceIQ')
    run.font.name  = 'Calibri'
    run.font.size  = Pt(36)
    run.font.bold  = True
    run.font.color.rgb = RGBColor(0xFF, 0xFF, 0xFF)
    title_p.paragraph_format.space_before = Pt(10)
    title_p.paragraph_format.space_after  = Pt(0)

    sub_p = doc.add_paragraph()
    sub_p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    shade_paragraph(sub_p, '1A56DB')
    run2 = sub_p.add_run('The Smarter Way to Run Your Business Billing')
    run2.font.name  = 'Calibri'
    run2.font.size  = Pt(14)
    run2.font.italic = True
    run2.font.color.rgb = RGBColor(0xBF, 0xDB, 0xFF)
    sub_p.paragraph_format.space_before = Pt(0)
    sub_p.paragraph_format.space_after  = Pt(10)

    tagline = doc.add_paragraph()
    tagline.alignment = WD_ALIGN_PARAGRAPH.CENTER
    shade_paragraph(tagline, 'EFF6FF')
    tr = tagline.add_run(
        'Built for Indian businesses. Designed to save your time, protect your money,\n'
        'and keep your GST filing stress-free — every single day.'
    )
    tr.font.name   = 'Calibri'
    tr.font.size   = Pt(11)
    tr.font.italic = True
    tr.font.color.rgb = BRAND_BLUE
    tagline.paragraph_format.space_before = Pt(8)
    tagline.paragraph_format.space_after  = Pt(14)

# ── Section heading (H2) ──────────────────────────────────────────────────────
def add_h2(text):
    p = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(18)
    p.paragraph_format.space_after  = Pt(4)
    run = p.add_run(text.upper())
    run.font.name  = 'Calibri'
    run.font.size  = Pt(13)
    run.font.bold  = True
    run.font.color.rgb = BRAND_BLUE
    # bottom border
    pPr  = p._p.get_or_add_pPr()
    pBdr = OxmlElement('w:pBdr')
    bot  = OxmlElement('w:bottom')
    bot.set(qn('w:val'),   'single')
    bot.set(qn('w:sz'),    '6')
    bot.set(qn('w:space'), '1')
    bot.set(qn('w:color'), '1A56DB')
    pBdr.append(bot)
    pPr.append(pBdr)

# ── Sub-heading (H3) ──────────────────────────────────────────────────────────
def add_h3(text):
    p = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(12)
    p.paragraph_format.space_after  = Pt(2)
    run = p.add_run(text)
    run.font.name  = 'Calibri'
    run.font.size  = Pt(11.5)
    run.font.bold  = True
    run.font.color.rgb = BRAND_DARK

# ── H4 label (Step 1 —, etc.) ────────────────────────────────────────────────
def add_h4(text):
    p = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(8)
    p.paragraph_format.space_after  = Pt(2)
    shade_paragraph(p, 'EFF6FF')
    run = p.add_run(text)
    run.font.name  = 'Calibri'
    run.font.size  = Pt(11)
    run.font.bold  = True
    run.font.color.rgb = BRAND_BLUE

# ── Body paragraph ────────────────────────────────────────────────────────────
def add_body(text, italic=False):
    p = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(2)
    p.paragraph_format.space_after  = Pt(6)
    render_inline(p, text)
    if italic:
        for run in p.runs:
            run.italic = True

# ── Bullet point ──────────────────────────────────────────────────────────────
def add_bullet(text, level=0):
    p = doc.add_paragraph(style='List Bullet')
    p.paragraph_format.left_indent   = Inches(0.25 + level * 0.25)
    p.paragraph_format.space_before  = Pt(1)
    p.paragraph_format.space_after   = Pt(1)
    render_inline(p, text)

# ── Numbered list ─────────────────────────────────────────────────────────────
def add_numbered(text):
    p = doc.add_paragraph(style='List Number')
    p.paragraph_format.left_indent  = Inches(0.25)
    p.paragraph_format.space_before = Pt(1)
    p.paragraph_format.space_after  = Pt(1)
    render_inline(p, text)

# ── Checkbox item ─────────────────────────────────────────────────────────────
def add_checkbox(text, checked=False):
    p = doc.add_paragraph()
    p.paragraph_format.left_indent  = Inches(0.25)
    p.paragraph_format.space_before = Pt(1)
    p.paragraph_format.space_after  = Pt(1)
    mark = '☑ ' if checked else '☐ '
    run = p.add_run(mark)
    run.font.size  = Pt(11)
    run.font.color.rgb = BRAND_ACCENT if checked else BRAND_MUTED
    render_inline(p, text)

# ── Blockquote / callout ──────────────────────────────────────────────────────
def add_quote(text):
    p = doc.add_paragraph()
    shade_paragraph(p, 'EFF6FF')
    p.paragraph_format.left_indent  = Inches(0.3)
    p.paragraph_format.space_before = Pt(6)
    p.paragraph_format.space_after  = Pt(6)
    run = p.add_run(text)
    run.font.name   = 'Calibri'
    run.font.size   = Pt(10.5)
    run.font.italic = True
    run.font.color.rgb = BRAND_BLUE

# ── Horizontal divider ────────────────────────────────────────────────────────
def add_divider():
    p = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(4)
    p.paragraph_format.space_after  = Pt(4)
    pPr  = p._p.get_or_add_pPr()
    pBdr = OxmlElement('w:pBdr')
    bot  = OxmlElement('w:bottom')
    bot.set(qn('w:val'),   'single')
    bot.set(qn('w:sz'),    '4')
    bot.set(qn('w:space'), '1')
    bot.set(qn('w:color'), 'D1D5DB')
    pBdr.append(bot)
    pPr.append(pBdr)

# ── Two-column table ──────────────────────────────────────────────────────────
def add_table(headers, rows, col_widths=None):
    tbl = doc.add_table(rows=1, cols=len(headers))
    tbl.alignment = WD_TABLE_ALIGNMENT.CENTER
    tbl.style = 'Table Grid'

    # header row
    hdr = tbl.rows[0]
    for i, h in enumerate(headers):
        cell = hdr.cells[i]
        shade_cell(cell, '1A56DB')
        set_cell_border(cell, color='1A56DB')
        cell.vertical_alignment = WD_ALIGN_VERTICAL.CENTER
        p = cell.paragraphs[0]
        p.alignment = WD_ALIGN_PARAGRAPH.LEFT
        p.paragraph_format.space_before = Pt(4)
        p.paragraph_format.space_after  = Pt(4)
        run = p.add_run(h)
        run.font.name  = 'Calibri'
        run.font.size  = Pt(10)
        run.font.bold  = True
        run.font.color.rgb = RGBColor(0xFF, 0xFF, 0xFF)

    # data rows
    for idx, row_data in enumerate(rows):
        row = tbl.add_row()
        fill = TABLE_ALT if idx % 2 == 0 else RGBColor(0xFF, 0xFF, 0xFF)
        fill_hex = 'EFF6FF' if idx % 2 == 0 else 'FFFFFF'
        for j, cell_text in enumerate(row_data):
            cell = row.cells[j]
            shade_cell(cell, fill_hex)
            set_cell_border(cell, color='D1D5DB')
            cell.vertical_alignment = WD_ALIGN_VERTICAL.CENTER
            p = cell.paragraphs[0]
            p.paragraph_format.space_before = Pt(3)
            p.paragraph_format.space_after  = Pt(3)
            render_inline(p, cell_text)
            for run in p.runs:
                run.font.name = 'Calibri'
                run.font.size = Pt(10)

    if col_widths:
        for i, w in enumerate(col_widths):
            for row in tbl.rows:
                row.cells[i].width = Inches(w)

    doc.add_paragraph().paragraph_format.space_after = Pt(4)

# ── Page break ────────────────────────────────────────────────────────────────
def add_page_break():
    doc.add_page_break()

# ══════════════════════════════════════════════════════════════════════════════
#  DOCUMENT CONTENT
# ══════════════════════════════════════════════════════════════════════════════

add_title_block()

# ── Section 1: What is InvoiceIQ ──────────────────────────────────────────────
add_h2('You Deserve More Than a Spreadsheet')
add_body(
    'Picture this: a customer asks for an invoice. You open a spreadsheet, copy last month\'s '
    'format, manually calculate GST, type the customer\'s address again (hoping it\'s correct), '
    'save it as PDF, attach it to an email, and send — only to realise you forgot to update the '
    'invoice number.'
)
add_quote('That ends today.')
add_body(
    'InvoiceIQ was built for exactly this moment. It handles the math, remembers your customers, '
    'generates your GST reports, and delivers a professional invoice to your customer — in minutes, '
    'not an hour.'
)
add_body(
    'Whether you run a consultancy, a trading business, a service agency, or a growing company, '
    'InvoiceIQ gives you the tools of a large enterprise — without needing an accountant sitting '
    'next to you.'
)

add_h3('Who Is This For?')
add_body('InvoiceIQ works for you if:')
add_bullet('You bill customers and want to look professional every time')
add_bullet('You deal with GST and want filing day to stop being a nightmare')
add_bullet('You have outstanding payments and want to know exactly who owes you what')
add_bullet('You want one place for all your billing — not scattered files on your desktop')

# ── Section 2: Roles ─────────────────────────────────────────────────────────
add_divider()
add_h2('Your Team, Your Roles')
add_body(
    'InvoiceIQ is built for teams of any size. Every person who logs in is assigned a role '
    'that controls what they can see and do.'
)
add_table(
    ['Role', 'What They Can Do'],
    [
        ['Admin',  'Complete control — invoices, customers, items, reports, settings, and user management'],
        ['Staff',  'Day-to-day billing — create and manage invoices, customers, and items'],
        ['Viewer', 'Read everything, change nothing — perfect for auditors, partners, or accountants who need visibility'],
    ],
    col_widths=[1.2, 5.0]
)
add_body('Your name and role are always visible in the sidebar. Everyone knows exactly what they are responsible for.')

# ── Section 3: Dashboard ─────────────────────────────────────────────────────
add_divider()
add_h2('The Dashboard — Know Your Business Before Your Morning Chai')
add_body(
    'The moment you log in, InvoiceIQ shows you everything that matters about your business — '
    'no digging, no calculating, no waiting.'
)
add_h3('At a Single Glance')
add_table(
    ['What You See', 'Why It Matters'],
    [
        ['Total Invoiced',   'How much business you have generated — ever'],
        ['Total Paid',       'Real money collected — your actual revenue'],
        ['Total Outstanding','What is still owed to you — your follow-up list'],
        ['Total Overdue',    'Money past its due date — your urgent list'],
        ['Invoice Count',    'Your volume of work in numbers'],
        ['Drafts',           'Invoices you prepared but haven\'t sent yet'],
        ['Customers',        'How large your client base has grown'],
    ],
    col_widths=[1.8, 4.4]
)
add_h3('Your Revenue Story — 6 Months at a Glance')
add_body(
    'A visual chart shows your monthly invoiced amounts for the last six months. Watch your growth. '
    'Spot your slow season. Plan your next push. This one chart tells you more about your business '
    'than a week of spreadsheet work.'
)
add_h3('Jump Straight Into Recent Work')
add_body(
    'Your 8 most recent invoices appear on the dashboard with customer name, amount, and status. '
    'One click and you are in. No searching, no scrolling.'
)

# ── Section 4: Invoices ───────────────────────────────────────────────────────
add_page_break()
add_h2('Invoices — Your Professionalism, Automated')
add_body(
    'An invoice is not just a document. It is how your customer sees your business. A clean, '
    'accurate, professional invoice builds trust. InvoiceIQ makes sure every invoice you send '
    'looks like it came from a company that means business.'
)

add_h3('Creating an Invoice in Minutes')
add_body('Navigate to **Invoices → New Invoice**.')

add_h4('Step 1 — Your Customer, Instantly')
add_body(
    'Start typing a customer\'s name. InvoiceIQ searches your database and suggests matches. '
    'Select one — and their full address, GSTIN, and billing details fill in automatically. '
    'No typing addresses from scratch. No copy-paste errors. The right details, every time.'
)
add_body(
    'First time billing this customer? Type their details manually, or add them to your '
    'Customers list first — and they will be waiting for you on every future invoice.'
)

add_h4('Step 2 — Invoice Identity')
add_bullet('**Invoice Number** — Auto-generated in your chosen format (e.g., INV-2025-0001). Sequential, unique, never repeated.')
add_bullet('**Invoice Date** — Pre-filled with today.')
add_bullet('**Due Date** — Set when you expect payment. Once this date passes, the invoice automatically turns Overdue.')
add_bullet('**Place of Supply** — The state of delivery. InvoiceIQ automatically determines whether to apply CGST+SGST or IGST.')

add_h4('Step 3 — Add What You Sold')
add_body('Click **Add Item** for each product or service on this invoice.')
add_table(
    ['Field', 'What to Enter'],
    [
        ['Item Name',    'What you sold or delivered'],
        ['HSN/SAC Code', 'Government classification code (important for GST filing)'],
        ['Qty',          'How many units'],
        ['Rate',         'Price per unit'],
        ['Discount %',   'Per-item discount, if any'],
        ['GST %',        '0%, 5%, 12%, 18%, or 28%'],
    ],
    col_widths=[1.6, 4.6]
)
add_body(
    'As you type, InvoiceIQ calculates every number live — taxable amount, CGST, SGST, IGST. '
    'No formulas. No chance of a wrong calculation reaching your customer.'
)

add_h4('Step 4 — Overall Discount (Optional)')
add_body(
    'Want to give your customer a special deal on the total invoice? Enter an invoice-level '
    'discount as a percentage or a flat rupee amount. GST adjusts automatically.'
)

add_h4('Step 5 — Your Message to the Customer')
add_body(
    'Add **Notes** (payment instructions, a thank-you line) and **Terms & Conditions** '
    '(your payment policy, late fee terms). These appear at the bottom of the printed invoice.'
)

add_h4('Step 6 — Send It')
add_bullet('**Save as Draft** — Not ready yet? Save and come back. Drafts are private until you send them.')
add_bullet('**Save & Send Email** — Invoice is saved, converted to PDF, and the email window opens ready for your customer.')

add_h3('Invoice Statuses — Always Know Where Every Invoice Stands')
add_table(
    ['Status', 'What It Means for You'],
    [
        ['Draft',     'In progress — your customer hasn\'t seen it yet'],
        ['Sent',      'Delivered — now you wait'],
        ['Partial',   'Some payment received — follow up for the rest'],
        ['Paid',      'Collected in full — mark it done and move on'],
        ['Overdue',   'Due date passed, still unpaid — time to follow up'],
        ['Cancelled', 'Voided — exists for records but excluded from all totals'],
    ],
    col_widths=[1.2, 5.0]
)

add_h3('Record Payments — Track Every Rupee That Comes In')
add_body(
    'When a customer pays — even partially — open the invoice and click **Record Payment**.'
)
add_table(
    ['Field', 'Purpose'],
    [
        ['Amount',       'How much was paid this time'],
        ['Payment Date', 'When you received it'],
        ['Mode',         'Cash, UPI, NEFT, RTGS, Cheque, Card, Other'],
        ['Reference No.','UTR, cheque number, or transaction ID'],
        ['Notes',        'Any note you want to attach'],
    ],
    col_widths=[1.6, 4.6]
)
add_body(
    'InvoiceIQ does the rest. Partial payment? Status automatically becomes **Partial**, '
    'and the balance due updates. Final payment? Status becomes **Paid**. Every payment '
    'is recorded on the invoice — so you always have proof.'
)

add_h3('Professional PDF — Your Invoice, Perfectly Presented')
add_body(
    'Click **Download PDF** on any invoice to get a polished, A4-format PDF with your '
    'company logo, customer details, complete line items with HSN codes, GST breakup, '
    'payment history, and your notes and terms.'
)
add_quote(
    'This is the document your customer files. This is the document your auditor inspects. '
    'It looks like it was designed by a professional — because it was.'
)

add_h3('Send by Email — Reach Your Customer in Seconds')
add_body(
    'Click **Send Email** from any invoice. A window appears pre-filled with the customer\'s '
    'address, a professional subject and body, and the PDF automatically attached. Hit Send '
    'and it\'s delivered. The invoice status updates from Draft to **Sent** automatically.'
)

# ── Section 5: Customers ──────────────────────────────────────────────────────
add_page_break()
add_h2('Customers — Your Business Runs on Relationships')
add_body(
    'Every business is really about people. The Customers section is where you build and '
    'maintain a record of every client relationship you have.'
)

add_h3('Build Your Client Database Once, Benefit Every Day')
add_body(
    'Adding a customer takes two minutes. After that, every invoice you raise for them fills '
    'in automatically — name, address, GSTIN, billing details, payment terms. No retyping. '
    'No risk of the wrong address on a legal document.'
)
add_body('**What you store for each customer:**')
add_bullet('Name, email, phone')
add_bullet('**GSTIN** — Critical for B2B invoices and GST reports')
add_bullet('**PAN**')
add_bullet('Billing and shipping addresses (separately, if different)')
add_bullet('Default payment terms for that client')
add_bullet('Credit limit — so you know when a client is approaching their ceiling')
add_bullet('Notes — anything important about this relationship')

add_h3('Customer Insights — Know the Value of Every Relationship')
add_body('Open any customer\'s record and instantly see:')
add_bullet('**Total invoiced** — How much you\'ve billed them over your entire relationship')
add_bullet('**Total outstanding** — How much they currently owe you')
add_bullet('**Full invoice history** — Every invoice, every status, in one place')
add_quote(
    '"You have ₹45,000 outstanding across three invoices" — said with confidence, not guesswork.'
)

# ── Section 6: Items ──────────────────────────────────────────────────────────
add_divider()
add_h2('Items — Your Catalog, Your Speed')
add_body(
    'Every time you create an invoice, you need the right item name, HSN code, rate, and GST '
    'percentage. If you sell the same products or services regularly, typing these manually '
    'every time is wasted effort — and a source of errors. Build your catalog once. Use it forever.'
)
add_h3('What Each Item Stores')
add_table(
    ['Field', 'Why It Matters'],
    [
        ['Name',             'What appears on the invoice'],
        ['Item Code',        'Your internal SKU or product code'],
        ['HSN/SAC Code',     'Required for GST reports — save it once, never look it up again'],
        ['Type',             'Goods or Service — determines GST classification in reports'],
        ['Default Rate',     'Your standard price per unit'],
        ['Default GST Rate', '0%, 5%, 12%, 18%, or 28%'],
        ['Unit',             'Nos, KG, Hours, Qty, etc.'],
        ['Description',      'Extra detail shown on invoices'],
    ],
    col_widths=[1.8, 4.4]
)
add_h3('The Magic of a Catalog')
add_body(
    'When adding a line item on an invoice, start typing the item name. InvoiceIQ immediately '
    'suggests matches. Select your item — and the HSN code, rate, GST rate, and unit fill in '
    'instantly.'
)
add_quote(
    'What used to take 2 minutes per line item now takes 5 seconds. '
    'Multiply that by every invoice you raise this year.'
)

# ── Section 7: GST Reports ────────────────────────────────────────────────────
add_page_break()
add_h2('GST Reports — Filing Day Is No Longer Stressful')
add_body(
    'GST compliance is non-negotiable. But it doesn\'t have to be a crisis at the end of every '
    'quarter. InvoiceIQ generates every report your CA needs — in the exact format they need it '
    '— in seconds. Every report can be exported to **Excel** (with multiple sheets) or **PDF** '
    '(print-ready with your company logo).'
)

add_h3('Choose Your Period')
add_body('Every report lets you filter by:')
add_bullet('This month / Last month')
add_bullet('This quarter / Last quarter')
add_bullet('This year / Last year')
add_bullet('**Custom date range** — your choice, any period')

add_h3('GSTR-1 Summary — Your Complete Sales Picture')
add_body(
    'A full list of all sales invoices for the selected period. Each invoice shows taxable value, '
    'CGST, SGST, IGST, total tax, and grand total. Automatically split into:'
)
add_bullet('**B2B** — Sales to registered businesses (customers with GSTIN)')
add_bullet('**B2C** — Sales to individuals and unregistered buyers')
add_body('Hand this to your CA and the bulk of GSTR-1 is done.')

add_h3('B2B Report — Know Every Registered Client You Billed')
add_body(
    'One row per registered customer. GSTIN, state, invoice count, taxable amount, full GST '
    'breakdown, and total. Sorted by value — so your largest accounts are always at the top. '
    'This is the data that goes directly into the B2B section of your GSTR-1 return.'
)

add_h3('B2C Report — Your Retail and Consumer Sales')
add_body(
    'Invoices to unregistered buyers, grouped by state and whether the sale was intra-state '
    'or inter-state. Exactly what your CA needs for the B2C section of GSTR-1.'
)

add_h3('HSN/SAC Summary — Product-Level Compliance Made Easy')
add_body(
    'All your sales grouped by HSN or SAC code. Total quantity, taxable value, CGST, SGST, '
    'IGST — per code, per GST rate. This is **Schedule B** in GSTR-1, which many businesses '
    'struggle to prepare. InvoiceIQ generates it automatically.'
)
add_quote('The only requirement: make sure you filled in HSN/SAC codes on your items.')

add_h3('Tax Liability Report — Plan Before You Pay')
add_body(
    'See exactly how much output tax you owe — broken down by GST rate (0%, 5%, 12%, 18%, 28%) '
    'and by **month**. No surprises on payment day. No scrambling to calculate what you owe. '
    'You see it clearly, well in advance.'
)

# ── Section 8: Settings ───────────────────────────────────────────────────────
add_page_break()
add_h2('Settings — Make InvoiceIQ Truly Yours')
add_body(
    'Settings is where InvoiceIQ transforms from a generic tool into your business\'s billing '
    'system. Spend 10 minutes here once, and every invoice you ever send carries your brand, '
    'your details, and your style.'
)
add_quote('Admin access only.')

add_h3('Your Company Identity')
add_body('Fill in your business details once — they appear on every invoice and every report:')
add_bullet('Company name, GSTIN, PAN')
add_bullet('Full address, city, state, state code')
add_bullet('Phone and email')
add_bullet('**Your logo** — Upload it (JPEG/PNG, max 2MB) and it appears on every invoice and report PDF you generate.')

add_h3('Your Bank Details')
add_body(
    'Add your bank account details — account number, IFSC, branch. These appear on your invoices '
    'so customers know exactly where to transfer payment. Reduces back-and-forth. Speeds up collection.'
)

add_h3('Invoice Numbering — Your Signature on Every Bill')
add_body(
    'Set a **prefix** that reflects your brand — INV, TRV, SRV, 2025 — and a starting number. '
    'InvoiceIQ auto-generates sequential invoice numbers: INV-2025-0001, INV-2025-0002, and so '
    'on, forever, without duplicates.'
)
add_quote('Consistent numbering is not just professional — it is legally required for GST compliance.')

add_h3('Invoice Defaults — Less Typing on Every Invoice')
add_bullet('**Default Payment Terms** — Set to 30 days and every new invoice already has a due date filled in.')
add_bullet('**Default Notes** — Write your standard thank-you or payment instruction once. It appears at the bottom of every invoice automatically.')

add_h3('Email Configuration — Send From Your Own Address')
add_body(
    'Configure SMTP once and send invoices directly from InvoiceIQ using your business email.'
)
add_table(
    ['Setting', 'Purpose'],
    [
        ['SMTP Host',        'Your provider\'s mail server (e.g., smtp.gmail.com)'],
        ['Port',             'Usually 587 (TLS) or 465 (SSL)'],
        ['Username + Password','Your email credentials'],
        ['From Name',        'What the customer sees as the sender'],
        ['From Email',       'The address they reply to'],
    ],
    col_widths=[1.8, 4.4]
)
add_body(
    '**Test SMTP button** — Before you send a single invoice, click Test SMTP. InvoiceIQ sends '
    'a test email and tells you if it works. Set it up right once, and it works silently forever.'
)
add_body(
    '**Default Subject and Body** — Pre-write your standard invoice email. It fills in '
    'automatically every time you send. Edit it per invoice when you want to personalise.'
)

# ── Section 9: User Management ────────────────────────────────────────────────
add_divider()
add_h2('User Management — The Right People, The Right Access')
add_body(
    'Your business isn\'t just you. InvoiceIQ lets you bring in your team — each with exactly '
    'the access level they need.'
)
add_quote('Admin access only.')
add_table(
    ['Role', 'Perfect For'],
    [
        ['Admin',  'Business owner, finance head — full control'],
        ['Staff',  'Sales team, billing executive — can create and manage everything except settings and users'],
        ['Viewer', 'Accountant, auditor, investor — can see the full picture without risk of changing anything'],
    ],
    col_widths=[1.2, 5.0]
)
add_body(
    'A protection is built in: you cannot accidentally delete your own account. '
    'InvoiceIQ makes sure you never lock yourself out.'
)

# ── Section 10: Quick-Start Checklist ─────────────────────────────────────────
add_page_break()
add_h2('Your Quick-Start Checklist')
add_body(
    'Before you raise your first invoice, spend 10 minutes setting up InvoiceIQ properly. '
    'You will never have to do this again.'
)
add_checkbox('Settings → Add company name, GSTIN, logo, and bank details')
add_checkbox('Settings → Set your invoice prefix and starting number')
add_checkbox('Settings → Configure email/SMTP and click Test SMTP')
add_checkbox('Items → Add your regularly sold products and services with HSN codes and default GST rates')
add_checkbox('Customers → Add your regular clients with their GSTIN')
add_quote(
    'Once this is done, creating a complete, GST-compliant, professional invoice takes under two minutes.'
)

# ── Section 11: Tips ──────────────────────────────────────────────────────────
add_divider()
add_h2('Tips From Experienced Users')

add_h3('Keep Your Items Catalog Complete')
add_body(
    'Every HSN code you fill in now saves you from manually entering it 200 times this year — '
    'and makes your HSN Summary report accurate without extra work.'
)
add_h3('Always Record Payments Promptly')
add_body(
    'The Outstanding and Overdue cards on your dashboard are only as accurate as your payment '
    'records. Record payments the same day you receive them, and your dashboard always tells the truth.'
)
add_h3('Save Every Customer — Even One-Time Clients')
add_body(
    'You never know who comes back. A customer saved today is an invoice raised in 30 seconds '
    'next time they call.'
)
add_h3('Use Draft Status Strategically')
add_body(
    'Prepare invoices for the month-end in advance as drafts. Review them together, then send '
    'in one session. Organised, professional, efficient.'
)
add_h3('Export Reports Before Your CA Asks')
add_body(
    'At the end of every month, export your GSTR-1 Summary and Tax Liability report. Keep them '
    'in a folder. When filing time comes, you are ready in five minutes.'
)

# ── Section 12: FAQ ───────────────────────────────────────────────────────────
add_page_break()
add_h2('Common Questions, Straight Answers')

faqs = [
    (
        'I sell to customers in other states. How does InvoiceIQ handle that?',
        'Automatically. When your state code and the customer\'s state code are different, '
        'InvoiceIQ charges IGST instead of CGST+SGST. You don\'t configure anything — it '
        'detects it from the Place of Supply on each invoice.'
    ),
    (
        'A customer paid part now and will pay the rest next month. What do I do?',
        'Record the first payment as you normally would. InvoiceIQ marks the invoice Partial '
        'and shows the balance due. When the second payment comes in, record that too. The '
        'invoice becomes Paid automatically. Full history, full audit trail.'
    ),
    (
        'I sent an invoice with a wrong amount. Can I fix it?',
        'Yes. Open the invoice, click Edit, fix it, and save. If you already emailed the '
        'original to the customer, send a revised copy with a note explaining the correction.'
    ),
    (
        'I want to cancel an invoice that was raised by mistake.',
        'Change the status to Cancelled. The invoice stays in the system for your records but '
        'disappears from all reports and totals. Nothing is ever deleted — only voided.'
    ),
    (
        'My accountant asks for a GST report every month. How fast can I send it?',
        'Under a minute. Open GST Reports, select the period, click Export Excel. You have a '
        'multi-sheet workbook with GSTR-1 summary, B2B, B2C, HSN summary, and tax liability — '
        'all in one file. Email it and move on.'
    ),
    (
        'What is Place of Supply and why does it matter?',
        'It is the state where goods are delivered or services are consumed — required by GST '
        'law on every invoice. InvoiceIQ uses it to automatically determine whether CGST+SGST '
        '(same state as your business) or IGST (different state) applies.'
    ),
]

for q, a in faqs:
    add_h4('Q: ' + q)
    add_body(a)

# ── Closing ───────────────────────────────────────────────────────────────────
add_divider()
closing = doc.add_paragraph()
closing.alignment = WD_ALIGN_PARAGRAPH.CENTER
shade_paragraph(closing, '1A56DB')
closing.paragraph_format.space_before = Pt(12)
closing.paragraph_format.space_after  = Pt(12)
run = closing.add_run(
    'InvoiceIQ — Built for Indian businesses. Trusted by people who mean business.'
)
run.font.name   = 'Calibri'
run.font.size   = Pt(12)
run.font.bold   = True
run.font.italic = True
run.font.color.rgb = RGBColor(0xFF, 0xFF, 0xFF)

# ── Save ──────────────────────────────────────────────────────────────────────
output = r'd:\xampp\htdocs\invoiceiq\InvoiceIQ_User_Guide.docx'
doc.save(output)
print(f'Saved: {output}')
