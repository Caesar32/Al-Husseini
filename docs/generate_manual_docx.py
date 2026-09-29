# -*- coding: utf-8 -*-
"""
مولّد دليل الاستخدام والتشغيل الشامل لمنظومة مجموعة الحسيني (الإصدار المحسّن المنسق)
- ترقيم واضح ومميز للخطوات في صفحات العمليات
- إدراج ترقيم الصفحات التلقائي في تذييل كل صفحة (صفحة X من Y)
- أيقونات ورموز منسقة وبصرية مريحة
- إزالة تامة لجميع الروابط والـ Routes التقنية واستبدالها بمسارات القوائم وأسماء الأزرار
"""

import os
import sys
import zipfile
import html

def xml_escape(text):
    if text is None:
        return ""
    return html.escape(str(text))

class DocxBuilder:
    def __init__(self, filename):
        self.filename = filename
        self.body_elements = []

    def add_p(self, text, style="Normal", align="both", bold=False, italic=False, color=None, size=24, space_before=120, space_after=120):
        """إضافة فقرة نصية عادية مع دعم RTL كامل"""
        pPr = f"""
        <w:pPr>
            <w:bidi/>
            <w:jc w:val="{align}"/>
            <w:spacing w:before="{space_before}" w:after="{space_after}" w:line="360" w:lineRule="auto"/>
            <w:rPr>
                <w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Arial"/>
                <w:rtl/>
                {"<w:b/>" if bold else ""}
                {"<w:i/>" if italic else ""}
                {f'<w:color w:val="{color}"/>' if color else ""}
                <w:sz w:val="{size}"/>
                <w:szCs w:val="{size}"/>
            </w:rPr>
        </w:pPr>
        """
        r = f"""
        <w:r>
            <w:rPr>
                <w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Arial"/>
                <w:rtl/>
                {"<w:b/>" if bold else ""}
                {"<w:i/>" if italic else ""}
                {f'<w:color w:val="{color}"/>' if color else ""}
                <w:sz w:val="{size}"/>
                <w:szCs w:val="{size}"/>
            </w:rPr>
            <w:t xml:space="preserve">{xml_escape(text)}</w:t>
        </w:r>
        """
        self.body_elements.append(f"<w:p>{pPr}{r}</w:p>")

    def add_h1(self, text, icon=""):
        """عنوان رئيسي كبير مع أيقونة منسقة"""
        full_text = f"{icon}  {text}" if icon else text
        self.add_p(full_text, align="right", bold=True, color="1B365D", size=34, space_before=360, space_after=160)

    def add_h2(self, text, icon=""):
        """عنوان فرعي أول"""
        full_text = f"{icon} {text}" if icon else text
        self.add_p(full_text, align="right", bold=True, color="2B6CB0", size=28, space_before=240, space_after=120)

    def add_h3(self, text):
        """عنوان فرعي ثاني"""
        self.add_p(text, align="right", bold=True, color="2C7A7B", size=24, space_before=160, space_after=80)

    def add_callout(self, text, title="معلومة تشغيلية مهمة:", bg_color="EDF2F7", border_color="3182CE", icon="💡"):
        """صندوق تنبيه أو معلومة ملونة مريح بصرياً"""
        table_xml = f"""
        <w:tbl>
            <w:tblPr>
                <w:tblW w:w="9600" w:type="dxa"/>
                <w:bidiVisual/>
                <w:jc w:val="center"/>
                <w:tblBorders>
                    <w:top w:val="none"/>
                    <w:left w:val="none"/>
                    <w:bottom w:val="none"/>
                    <w:right w:val="single" w:sz="36" w:space="0" w:color="{border_color}"/>
                    <w:insideH w:val="none"/>
                    <w:insideV w:val="none"/>
                </w:tblBorders>
            </w:tblPr>
            <w:tr>
                <w:tc>
                    <w:tcPr>
                        <w:tcW w:w="9600" w:type="dxa"/>
                        <w:shd w:val="clear" w:color="auto" w:fill="{bg_color}"/>
                        <w:tcMar>
                            <w:top w:w="160" w:type="dxa"/>
                            <w:left w:w="240" w:type="dxa"/>
                            <w:bottom w:w="160" w:type="dxa"/>
                            <w:right w:w="240" w:type="dxa"/>
                        </w:tcMar>
                    </w:tcPr>
                    <w:p>
                        <w:pPr><w:bidi/><w:jc w:val="right"/><w:spacing w:before="60" w:after="60"/></w:pPr>
                        <w:r>
                            <w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Arial"/><w:rtl/><w:b/><w:color w:val="{border_color}"/><w:sz w:val="24"/><w:szCs w:val="24"/></w:rPr>
                            <w:t xml:space="preserve">{icon} {xml_escape(title)} </w:t>
                        </w:r>
                        <w:r>
                            <w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Arial"/><w:rtl/><w:sz w:val="22"/><w:szCs w:val="22"/><w:color w:val="2D3748"/></w:rPr>
                            <w:t xml:space="preserve">{xml_escape(text)}</w:t>
                        </w:r>
                    </w:p>
                </w:tc>
            </w:tr>
        </w:tbl>
        """
        self.body_elements.append(table_xml)

    def add_step(self, step_num, title, description):
        """خطوة تنفيذية مرقمة ببطاقة بصرية مستقلة وواضحة جداً"""
        table_xml = f"""
        <w:tbl>
            <w:tblPr>
                <w:tblW w:w="9600" w:type="dxa"/>
                <w:bidiVisual/>
                <w:jc w:val="center"/>
                <w:tblBorders>
                    <w:top w:val="single" w:sz="4" w:space="0" w:color="E2E8F0"/>
                    <w:left w:val="single" w:sz="4" w:space="0" w:color="E2E8F0"/>
                    <w:bottom w:val="single" w:sz="4" w:space="0" w:color="E2E8F0"/>
                    <w:right w:val="single" w:sz="24" w:space="0" w:color="2B6CB0"/>
                    <w:insideH w:val="none"/>
                    <w:insideV w:val="none"/>
                </w:tblBorders>
            </w:tblPr>
            <w:tr>
                <!-- عمود رقم الخطوة الملون -->
                <w:tc>
                    <w:tcPr>
                        <w:tcW w:w="1400" w:type="dxa"/>
                        <w:shd w:val="clear" w:color="auto" w:fill="EBF8FF"/>
                        <w:tcMar>
                            <w:top w:w="120" w:type="dxa"/><w:left w:w="120" w:type="dxa"/><w:bottom w:w="120" w:type="dxa"/><w:right w:w="120" w:type="dxa"/>
                        </w:tcMar>
                    </w:tcPr>
                    <w:p>
                        <w:pPr><w:bidi/><w:jc w:val="center"/><w:spacing w:before="60" w:after="60"/></w:pPr>
                        <w:r>
                            <w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Arial"/><w:rtl/><w:b/><w:color w:val="2B6CB0"/><w:sz w:val="26"/><w:szCs w:val="26"/></w:rPr>
                            <w:t xml:space="preserve">خطوة {step_num}</w:t>
                        </w:r>
                    </w:p>
                </w:tc>
                <!-- عمود العنوان والشرح المفصل -->
                <w:tc>
                    <w:tcPr>
                        <w:tcW w:w="8200" w:type="dxa"/>
                        <w:shd w:val="clear" w:color="auto" w:fill="F7FAFC"/>
                        <w:tcMar>
                            <w:top w:w="120" w:type="dxa"/><w:left w:w="180" w:type="dxa"/><w:bottom w:w="120" w:type="dxa"/><w:right w:w="180" w:type="dxa"/>
                        </w:tcMar>
                    </w:tcPr>
                    <w:p>
                        <w:pPr><w:bidi/><w:jc w:val="right"/><w:spacing w:before="40" w:after="40"/><w:line w:line="320" w:lineRule="auto"/></w:pPr>
                        <w:r>
                            <w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Arial"/><w:rtl/><w:b/><w:color w:val="1A202C"/><w:sz w:val="24"/><w:szCs w:val="24"/></w:rPr>
                            <w:t xml:space="preserve">{xml_escape(title)}: </w:t>
                        </w:r>
                        <w:r>
                            <w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Arial"/><w:rtl/><w:color w:val="4A5568"/><w:sz w:val="22"/><w:szCs w:val="22"/></w:rPr>
                            <w:t xml:space="preserve">{xml_escape(description)}</w:t>
                        </w:r>
                    </w:p>
                </w:tc>
            </w:tr>
        </w:tbl>
        """
        self.body_elements.append(table_xml)
        # مسافة فاصلة خفيفة بعد البطاقة
        self.body_elements.append("""<w:p><w:pPr><w:bidi/><w:spacing w:before="60" w:after="60"/></w:pPr></w:p>""")

    def add_bullet(self, title, description="", icon="🔹"):
        """نقطة في قائمة مع رمز بصري"""
        pPr = """
        <w:pPr>
            <w:bidi/>
            <w:jc w:val="both"/>
            <w:spacing w:before="60" w:after="60" w:line="320" w:lineRule="auto"/>
        </w:pPr>
        """
        desc_xml = f"""<w:r><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Arial"/><w:rtl/><w:sz w:val="22"/><w:szCs w:val="22"/><w:color w:val="4A5568"/></w:rPr><w:t xml:space="preserve"> {xml_escape(description)}</w:t></w:r>""" if description else ""
        r = f"""
        <w:r>
            <w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Arial"/><w:rtl/><w:b/><w:color w:val="1A202C"/><w:sz w:val="22"/><w:szCs w:val="22"/></w:rPr>
            <w:t xml:space="preserve">{icon} {xml_escape(title)}:</w:t>
        </w:r>
        {desc_xml}
        """
        self.body_elements.append(f"<w:p>{pPr}{r}</w:p>")

    def add_table(self, headers, rows):
        """جدول بيانات بتنسيق كامل وجميل مع دعم RTL وخالي من أي لينكات تقنية"""
        tbl_pr = """
        <w:tblPr>
            <w:tblW w:w="9600" w:type="dxa"/>
            <w:bidiVisual/>
            <w:jc w:val="center"/>
            <w:tblBorders>
                <w:top w:val="single" w:sz="6" w:space="0" w:color="CBD5E0"/>
                <w:left w:val="single" w:sz="6" w:space="0" w:color="CBD5E0"/>
                <w:bottom w:val="single" w:sz="6" w:space="0" w:color="CBD5E0"/>
                <w:right w:val="single" w:sz="6" w:space="0" w:color="CBD5E0"/>
                <w:insideH w:val="single" w:sz="4" w:space="0" w:color="E2E8F0"/>
                <w:insideV w:val="single" w:sz="4" w:space="0" w:color="E2E8F0"/>
            </w:tblBorders>
        </w:tblPr>
        """

        header_xml = "<w:tr>"
        for h in headers:
            header_xml += f"""
            <w:tc>
                <w:tcPr>
                    <w:shd w:val="clear" w:color="auto" w:fill="2B6CB0"/>
                    <w:tcMar>
                        <w:top w:w="140" w:type="dxa"/><w:left w:w="160" w:type="dxa"/><w:bottom w:w="140" w:type="dxa"/><w:right w:w="160" w:type="dxa"/>
                    </w:tcMar>
                </w:tcPr>
                <w:p>
                    <w:pPr><w:bidi/><w:jc w:val="center"/><w:spacing w:before="40" w:after="40"/></w:pPr>
                    <w:r>
                        <w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Arial"/><w:rtl/><w:b/><w:color w:val="FFFFFF"/><w:sz w:val="22"/><w:szCs w:val="22"/></w:rPr>
                        <w:t xml:space="preserve">{xml_escape(h)}</w:t>
                    </w:r>
                </w:p>
            </w:tc>
            """
        header_xml += "</w:tr>"

        rows_xml = ""
        for i, row in enumerate(rows):
            bg = "F7FAFC" if i % 2 == 1 else "FFFFFF"
            rows_xml += "<w:tr>"
            for col_idx, c in enumerate(row):
                # العمود الأول عريض قليلاً
                is_bold = (col_idx == 0)
                text_align = "center" if col_idx == 1 else "right"
                rows_xml += f"""
                <w:tc>
                    <w:tcPr>
                        <w:shd w:val="clear" w:color="auto" w:fill="{bg}"/>
                        <w:tcMar>
                            <w:top w:w="120" w:type="dxa"/><w:left w:w="140" w:type="dxa"/><w:bottom w:w="120" w:type="dxa"/><w:right w:w="140" w:type="dxa"/>
                        </w:tcMar>
                    </w:tcPr>
                    <w:p>
                        <w:pPr><w:bidi/><w:jc w:val="{text_align}"/><w:spacing w:before="30" w:after="30"/></w:pPr>
                        <w:r>
                            <w:rPr>
                                <w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Arial"/><w:rtl/>
                                {"<w:b/>" if is_bold else ""}
                                <w:sz w:val="20"/><w:szCs w:val="20"/>
                                <w:color w:val="{'1A202C' if is_bold else '4A5568'}"/>
                            </w:rPr>
                            <w:t xml:space="preserve">{xml_escape(str(c))}</w:t>
                        </w:r>
                    </w:p>
                </w:tc>
                """
            rows_xml += "</w:tr>"

        table_full = f"<w:tbl>{tbl_pr}{header_xml}{rows_xml}</w:tbl>"
        self.body_elements.append(table_full)

    def add_page_break(self):
        self.body_elements.append('<w:p><w:r><w:br w:type="page"/></w:r></w:p>')

    def save(self):
        content_types = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>
</Types>"""

        rels = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>"""

        doc_rels = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
  <Relationship Id="rIdFooter" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/>
</Relationships>"""

        footer_xml = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
       xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <w:p>
    <w:pPr>
      <w:bidi/>
      <w:jc w:val="center"/>
      <w:pBdr>
        <w:top w:val="single" w:sz="6" w:space="6" w:color="CBD5E0"/>
      </w:pBdr>
      <w:spacing w:before="120" w:after="0"/>
    </w:pPr>
    <w:r>
      <w:rPr>
        <w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Arial"/>
        <w:rtl/>
        <w:sz w:val="18"/>
        <w:szCs w:val="18"/>
        <w:color w:val="718096"/>
      </w:rPr>
      <w:t xml:space="preserve">منظومة مجموعة الحسيني لإدارة الفروع ونقاط البيع   |   صفحة </w:t>
    </w:r>
    <w:fldSimple w:instr="PAGE"/>
    <w:r>
      <w:rPr>
        <w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Arial"/>
        <w:rtl/>
        <w:sz w:val="18"/>
        <w:szCs w:val="18"/>
        <w:color w:val="718096"/>
      </w:rPr>
      <w:t xml:space="preserve"> من </w:t>
    </w:r>
    <w:fldSimple w:instr="NUMPAGES"/>
  </w:p>
</w:ftr>"""

        styles_xml = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:docDefaults>
    <w:rPrDefault>
      <w:rPr>
        <w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Arial"/>
        <w:sz w:val="22"/>
        <w:szCs w:val="22"/>
        <w:rtl/>
      </w:rPr>
    </w:rPrDefault>
    <w:pPrDefault>
      <w:pPr>
        <w:bidi/>
        <w:jc w:val="both"/>
      </w:pPr>
    </w:pPrDefault>
  </w:docDefaults>
</w:styles>"""

        body_content = "".join(self.body_elements)
        document_xml = f"""<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
            xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <w:body>
    {body_content}
    <w:sectPr>
      <w:footerReference w:type="default" r:id="rIdFooter"/>
      <w:pgSz w:w="11906" w:h="16838"/>
      <w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="720" w:footer="720" w:gutter="0"/>
      <w:bidi/>
    </w:sectPr>
  </w:body>
</w:document>"""

        os.makedirs(os.path.dirname(os.path.abspath(self.filename)), exist_ok=True)
        with zipfile.ZipFile(self.filename, 'w', zipfile.ZIP_DEFLATED) as z:
            z.writestr('[Content_Types].xml', content_types)
            z.writestr('_rels/.rels', rels)
            z.writestr('word/_rels/document.xml.rels', doc_rels)
            z.writestr('word/footer1.xml', footer_xml)
            z.writestr('word/styles.xml', styles_xml)
            z.writestr('word/document.xml', document_xml)

        try:
            print(f"File created successfully: {os.path.basename(self.filename)}")
        except Exception:
            print("File created successfully.")


def build_alhusseini_master_manual(output_path):
    doc = DocxBuilder(output_path)

    # ══════════════════════════════════════════════════════════════
    # صفحة الغلاف والتعريف
    # ══════════════════════════════════════════════════════════════
    doc.add_p("مجموعة الحسيني لإدارة الفروع ونقاط البيع والموارد البشرية", align="center", bold=True, color="D4AF37", size=28, space_before=720, space_after=120)
    doc.add_p("الدليل التشغيلي الشامل لكل شاشات وعمليات النظام", align="center", bold=True, color="1B365D", size=44, space_before=120, space_after=240)
    doc.add_p("مشروح خطوة بخطوة بالعامية المصرية المبسطة مع أسرار الحسابات والمعادلات والرقابة المالية", align="center", italic=True, color="4A5568", size=24, space_before=60, space_after=480)

    doc.add_callout(
        "الفرع التشغيلي النشط حالياً للمجموعة: فرع دمياط الجديدة الرئيسي (شارع المحجوب، دمياط الجديدة). النظام مصمم بمعمارية ربط متعدد الفروع تدعم فتح أي عدد من الفروع مستقبلاً مع عزل كامل للبيانات وأمان محكم.",
        title="الفرع والتشغيل الفعلي المعتمد:",
        bg_color="FEFCBF",
        border_color="D69E2E",
        icon="🏢"
    )

    doc.add_p("──────────────────────────────────────────────────────────", align="center", color="CBD5E0", size=20, space_before=240, space_after=240)
    doc.add_bullet("المستخدم المستهدف", "المشرف العام، المدير المالي، مسؤولو المبيعات والكاشير، مسؤولو المشتريات، ومديرو الورشة ومسؤولو الموارد البشرية", icon="👥")
    doc.add_bullet("أسلوب العمل في الدليل", "خطوات تنفيذية مرقمة بوضوح مع مسارات القوائم وأسماء الشاشات والأزرار المباشرة بدون أي تعقيدات تقنية", icon="🎯")
    doc.add_bullet("تاريخ الإصدار والجاهزية", "سبتمبر 2026 - جاهز للتشغيل والطباعة مع التدقيق الكامل لجميع العمليات الحسابية والمالية", icon="✅")
    
    doc.add_page_break()

    # ══════════════════════════════════════════════════════════════
    # الفصل الأول: إزاي تبدأ وتسجل دخول وتفهم الأمان والصلاحيات
    # ══════════════════════════════════════════════════════════════
    doc.add_h1("الفصل الأول: البداية وتسجيل الدخول وأسرار الأمان", icon="🔑")
    doc.add_p("النظام معمول عشان يديك أقصى سرعة في الشغل مع حماية كاملة لحساباتك ومخزنك. في الفصل ده هنعرف إزاي تفتح النظام وتتعامل مع الحسابات وشاشة القفل.")

    doc.add_h2("أولاً: خطوات تسجيل الدخول بحسابك في النظام", icon="📌")
    doc.add_step(1, "فتح شاشة الدخول", "افتح المتصفح الخاص بجهاز العمل في المحل، هتظهرلك واجهة الدخول الرسمية لمجموعة الحسيني.")
    doc.add_step(2, "إدخال بيانات الحساب", "اكتب البريد الإلكتروني أو رقم الهاتف المعتمد لك، ثم اكتب كلمة المرور السرية الخاصة بك.")
    doc.add_step(3, "التوجيه التلقائي الذكي للكاشير", "لو حسابك مسجل بصلاحية كاشير مبيعات، النظام هينقلك أوتوماتيك وفوراً لشاشة البيع (نقطة البيع POS) بدون ما يدخلك على الإحصائيات العامة؛ عشان تبدأ تسجل فواتير الزباين بسرعة وبدون تضييع وقت.")

    doc.add_h2("ثانياً: شاشة القفل التلقائية لحماية الجهاز (Lock Screen)", icon="🔒")
    doc.add_p("لو قمت من قدام جهازك وسبته لدقايق، اضغط على زرار قفل الشاشة من الشريط العلوي عشان تمنع أي شخص غريب من لمس الفواتير أو الحسابات.")
    doc.add_callout(
        "نظام الحماية هنا صارم جداً؛ لو حد حاول يخمن كلمة السر وكتبها 5 مرات متتالية بالخطأ، النظام بيقفل الشاشة فوراً لمدة 5 دقائق ويمنع أي محاولة جديدة لمنع التخمين. بعد انتهاء المدة بيفتحلك المحاولة من جديد.",
        title="حماية شاشة القفل ضد المحاولات الخاطئة:",
        bg_color="FED7D7",
        border_color="E53E3E",
        icon="⚠️"
    )

    doc.add_h2("ثالثاً: إدارة المستخدمين والأدوار الوظيفية", icon="⚙️")
    doc.add_p("لو أنت المشرف العام أو المدير الإداري، تقدر تتحكم في كل موظف من خلال القائمة الجانبية ⬅️ قسم 'إدارة المستخدمين والصلاحيات':")
    doc.add_bullet("إضافة مستخدم جديد", "بتحدد الاسم، الإيميل، رقم التليفون، كلمة المرور، والفرع التابع له الموظف.", icon="➕")
    doc.add_bullet("تحديد الدور الوظيفي", "بتختار الدور المناسب: كاشير (يبيع فقط)، فني ورشة (تسجيل فحص وضمان)، محاسب (مشتريات ومدفوعات)، أو مدير عام (اعتمادات وتقارير كاملة).", icon="🛡️")
    doc.add_bullet("تعطيل وتفعيل الحساب فوراً", "بضغطة زرار واحدة تقدر توقف حساب أي موظف فوراً لو ساب العمل بدون ما تمسح بياناته التاريخية والفواتير اللي سجلها مسبقاً.", icon="🛑")

    doc.add_page_break()

    # ══════════════════════════════════════════════════════════════
    # الفصل الثاني: كاشير نقطة البيع (POS) والمبيعات والورشة
    # ══════════════════════════════════════════════════════════════
    doc.add_h1("الفصل الثاني: كاشير نقطة البيع (POS) والمبيعات والورشة", icon="🛒")
    doc.add_p("شاشة نقطة البيع (POS) هي قلب المحل التشغيلي؛ اتصممت عشان تخلص الفاتورة في أقل من دقيقة حتى لو كان فيها أصناف كتير، بطارية كهنة قديمة، دفع متقسم، وإصدار ضمان.")

    doc.add_h2("أولاً: خطوات إصدار فاتورة بيع جديدة بالتفصيل", icon="📝")
    doc.add_step(1, "الدخول على شاشة البيع", "من القائمة الجانبية: اختر قسم المبيعات ⬅️ ثم اضغط على 'كاشير نقطة البيع (POS)'.")
    doc.add_step(2, "اختيار العميل وبيانات المركبة", "اكتب اسم العميل أو رقم هاتفه. لو عميل مسجل سابقاً هيظهر رصيده وسقف ائتمانه وعربيته ورقم لوحتها. لو عميل نقدي عابر، اضغط 'عميل نقدي سريع'.")
    doc.add_step(3, "تحديد الفني / العامل القائم بالتركيب (إجباري)", "اختر اسم الفني أو العامل المسؤول عن التركيب بالورشة (حقل إجباري في الفاتورة لتوثيق المسؤولية وضمان الجودة).")
    doc.add_step(4, "إضافة الأصناف وسيريال البطارية", "اختر الصنف المطلوب. لو الصنف بطارية سيارة، النظام هيطلب منك إدخال رقم السيريال الفريد المطبوع على البطارية (وده إجباري لتفعيل الضمان ومنع التكرار).")
    doc.add_step(5, "استبدال بطارية قديمة (خصم كهنة)", "لو العميل هيسلم بطاريته القديمة، اضغط علامة 'استبدال كهنة' واكتب أمبير البطارية القديمة (مثلاً 70 أمبير) وقيمة الخصم المتفق عليها (مثلاً 700 جنيه). الفاتورة هتطرح الـ 700 جنيه فوراً من الصافي المطلوب دفعه!")
    doc.add_step(6, "توزيع طرق الدفع المتعددة", "العميل يقدر يدفع بطريقة واحدة أو يقسم المبلغ: جزء كاش، جزء شبكة وفيزا، وجزء آجل على حسابه الشخصي بشرط أن يغطي الإجمالي صافي الفاتورة بالكامل.")
    doc.add_step(7, "حفظ وإصدار الفاتورة فوراً", "اضغط زر 'حفظ وطباعة الفاتورة'. في ثانية واحدة النظام هيطبع الإيصال وينفذ الإجراءات المحاسبية والمخزنية خلف الكواليس.")

    doc.add_callout(
        "بمجرد ضغط زر حفظ الفاتورة، النظام بيعمل الآتي في ثانية واحدة:\n1. خصم البطارية المباعة من رصيد المخزن فوراً وقفل السجل لمنع البيع السالب.\n2. إنشاء شهادة ضمان إلكترونية نشطة برقم السيريال ورقم لوحة السيارة.\n3. إضافة البطارية القديمة المستلمة تلقائياً لمخزن الكهنة برصيد رصاصها.\n4. توثيق وربط اسم العامل / الفني القائم بالتركيب في الفاتورة والضمان وسند التسليم.\n5. ترحيل المبلغ الآجل إلى مديونية العميل بدفتر الأستاذ العام بالرصيد قبل وبعد بدقة تامة.",
        title="ماذا يحدث في ثانية واحدة عند حفظ الفاتورة؟",
        bg_color="E6FFFA",
        border_color="319795",
        icon="⚡"
    )

    doc.add_h2("ثانياً: سقف الائتمان الثابت (5,000 ج.م) والتعامل مع التجاوز", icon="💳")
    doc.add_p("الحد الائتماني الافتراضي الثابت للعملاء في المنظومة هو 5000.00 جنيه. لو عميل حاول يشتري بضاعة آجل بقيمة تجعل مديونيته تتجاوز الـ 5000 جنيه (أو سقف حسابه المعتمد):")
    doc.add_bullet("الرفض التلقائي للحماية", "النظام هيطلع تنبيه برتقالي ويمنع الكاشير من إتمام البيع بالآجل حمايةً لأموال المنشأة.", icon="🚫")
    doc.add_bullet("الموافقة بكود تفويض المدير المالي", "يقدر الكاشير يطلب استثناء من المدير، والمدير يدخل 'كود التفويض الإداري' في الخانة المخصصة، وقتها الفاتورة بتمر وتتسجل بموافقة إدارية رسمية.", icon="🔑")

    doc.add_page_break()

    # ══════════════════════════════════════════════════════════════
    # الفصل الثالث: المشتريات والتوريدات وتسعير المتوسط المرجح (WAC)
    # ══════════════════════════════════════════════════════════════
    doc.add_h1("الفصل الثالث: المشتريات وحساب التكلفة بالمتوسط المرجح", icon="📦")
    doc.add_p("في الفصل ده هنشرح إزاي تسجل شحنة بضاعة واردة من شركات ومصانع البطاريات، وإزاي النظام بيحسب تكلفة الصنف بالمليم لمنع أي تضارب في أرباح المحل.")

    doc.add_h2("أولاً: خطوات تسجيل فاتورة توريد بضاعة جديدة", icon="📥")
    doc.add_step(1, "الدخول على شاشة المشتريات", "من القائمة الجانبية: اختر قسم المشتريات والتوريدات ⬅️ اضغط زر 'إضافة فاتورة توريد جديدة'.")
    doc.add_step(2, "اختيار المورد والفرع المستلم", "اختر شركة التوريد (مثلاً: شركة النسر للبطاريات)، ثم حدد فرع دمياط الجديدة المستلم وتاريخ الاستلام ورقم فاتورة المورد الورقية.")
    doc.add_step(3, "إضافة الأصناف وأسعار التكلفة", "اختر الأصناف، حدد الكميات المستلمة، وسعر تكلفة القطعة في هذه الشحنة، ورقم التشغيلة إن وجد.")
    doc.add_step(4, "شروط السداد والدفعات", "حدد المبلغ المسدد فوراً للمورد من الخزينة، والمبلغ المتبقي كآجل لحساب المورد بدفتر الأستاذ.")
    doc.add_step(5, "اعتماد الفاتورة وتحديث المخزن", "اضغط زر 'حفظ واعتماد التوريد'. رصيد البضاعة هيزيد في المخزن فوراً، وهيتم إعادة حساب متوسط التكلفة آلياً.")

    doc.add_h2("ثانياً: فكرة معادلة المتوسط المرجح للتكلفة ببساطة", icon="📊")
    doc.add_p("لو كان عندك في المخزن 10 بطاريات قديمة بتكلفة 1000 جنيه للواحدة. واشتريت شحنة جديدة 10 بطاريات بسعر 1200 جنيه:")
    doc.add_bullet("قيمة الرصيد القديم بالمخزن", "10 بطاريات × 1000 جنيه = 10,000 جنيه.", icon="1️⃣")
    doc.add_bullet("قيمة الشحنة الجديدة الموردة", "10 بطاريات × 1200 جنيه = 12,000 جنيه.", icon="2️⃣")
    doc.add_bullet("إجمالي القيمة مقسومة على إجمالي العدد", "22,000 جنيه إجمالي ÷ 20 بطارية متوفرة.", icon="3️⃣")
    doc.add_bullet("التكلفة الجديدة المعتمدة في النظام", "1100.00 جنيه للقطعة الواحدة تلقائياً!", icon="✅")

    doc.add_callout(
        "النظام بيحدث حقل تكلفة الصنف بالرقم ده فوراً، وبالتالي لما الكاشير يبيع أي بطارية بعد كدة، تقرير أرباحك الصافية بيتحسب على 1100 جنيه بدقة متناهية وبدون أي لخبطة محاسبية.",
        title="الفائدة المحاسبية للمتوسط المرجح:",
        bg_color="EBF8FF",
        border_color="3182CE",
        icon="💡"
    )

    doc.add_page_break()

    # ══════════════════════════════════════════════════════════════
    # الفصل الرابع: إدارة الضمانات والاستبدال الفوري
    # ══════════════════════════════════════════════════════════════
    doc.add_h1("الفصل الرابع: الضمانات والاستبدال الفوري وتذاكر الموردين", icon="🛡️")
    doc.add_p("تجارة البطاريات قايمة على سمعة وسرعة خدمة الضمان. النظام فيه محرك فحص سريع واستبدال فوري مريح للعميل ومحكم لحق المحل.")

    doc.add_h2("أولاً: خطوات فحص سريان الضمان برقم السيريال", icon="🔍")
    doc.add_step(1, "الدخول على شاشة الفحص", "من القائمة الجانبية: اختر قسم الضمانات ⬅️ ثم اضغط على 'فحص سريان الضمان بالسيريال'.")
    doc.add_step(2, "إدخال السيريال أو المسح بالباركود", "اكتب أو امسح بقارئ الباركود رقم السيريال المطبوع على البطارية التي أحضرها العميل.")
    doc.add_step(3, "معاينة النتيجة الفورية", "النظام هيعرض بطاقة ملونة توضح: اسم العميل، بيانات السيارة، تاريخ البيع، تاريخ نهاية الضمان، وعدد الأيام المتبقية وحالة السريان (ساري أو منتهي).")

    doc.add_h2("ثانياً: خطوات الاستبدال الفوري للبطارية المعيبة", icon="🔄")
    doc.add_p("إذا تبين بعد فحص الفني بالورشة وجود عيب صناعة في البطارية والضمان سارٍ:")
    doc.add_step(1, "بدء إجراء المطالبة", "من نفس بطاقة الفحص، اضغط زر 'تسجيل استبدال فوري للعميل'.")
    doc.add_step(2, "اختيار البطارية البديلة", "اختر البطارية الجديدة من المخزن وسجل رقم السيريال الجديد الخاص بها.")
    doc.add_step(3, "تسجيل قراءات الفحص الفني", "اكتب قراءة الفولت واختبار كفاءة التشغيل (CCA) وسبب العطل الفني المكتشف.")
    doc.add_step(4, "الاعتماد والتسليم الفوري", "اضغط زر 'اعتماد الاستبدال وتسليم العميل'. العميل يستلم بطاريته الجديدة في دقائق، والنظام ينفذ الآتي أوتوماتيك:")
    doc.add_bullet("صرف البديلة من المخزن", "خصم بطارية جديدة من رصيد المحل للعميل.", icon="📦")
    doc.add_bullet("إلغاء سيريال البطارية القديمة", "تغيير حالة البطارية التالفة إلى (تم استبدالها) لمنع استخدام نفس السيريال في أي مطالبة أخرى مستقبلاً.", icon="🔒")
    doc.add_bullet("إنشاء تذكرة مطالبة موجهة للمصنع", "إصدار تذكرة ضمان رسمية موجهة لشركة التوريد عشان ترجعلك بطارية جديدة مكانها.", icon="📋")

    doc.add_page_break()

    # ══════════════════════════════════════════════════════════════
    # الفصل الخامس: مخزن الكهنة وإعادة تدوير الرصاص
    # ══════════════════════════════════════════════════════════════
    doc.add_h1("الفصل الخامس: مخزن الكهنة وتجارة رصاص الصهر", icon="♻️")
    doc.add_p("البطاريات القديمة اللي الزباين بيسيبوها تمثل منجم أرباح إضافي للمحل إذا اتدارت بشكل علمي ومرتب.")

    doc.add_h2("أولاً: إزاي البطاريات القديمة بتدخل حوش الكهنة؟", icon="📥")
    doc.add_p("بمجرد ما الكاشير يفعل خيار 'استبدال كهنة' في فاتورة المبيعات، البطارية بتروح أوتوماتيك لصفحة 'مخزن بطاريات الكهنة':")
    doc.add_bullet("حساب وزن الرصاص التقديري", "كل أمبير بطارية بيحتوي في المتوسط على 0.17 كجم رصاص صافي. النظام بيجمع أمبيرات الكهنة ويحسب إجمالي وزن الرصاص بالكيلو والطن أوتوماتيك.", icon="⚖️")
    doc.add_bullet("سلسلة العهدة والمسؤولية", "كل بطارية في الحوش متسجل عليها تاريخ استلامها، اسم الفني اللي استلمها، ورقم فاتورة العميل الأصلية.", icon="🏷️")

    doc.add_h2("ثانياً: خطوات بيع لوط كهنة لمصنع تدوير وصهر الرصاص", icon="🚛")
    doc.add_step(1, "تحديد البطاريات الجاهزة للبيع", "من القائمة الجانبية: اختر قسم 'مخزن بطاريات الكهنة'، ثم علم على البطاريات المراد بيعها في الشحنة.")
    doc.add_step(2, "إدخال بيانات المشتري وسعر الصفقة", "اضغط زر 'بيع دفعة لمصنع التدوير'، واكتب اسم مصنع الصهر وسعر البيع المتفق عليه لإجمالي اللوط أو لكل كيلو رصاص.")
    doc.add_step(3, "اعتماد البيع والتأثير المالي", "اضغط 'تأكيد البيع'. النظام هيخرج البطاريات من الحوش ويحول حالتها إلى (تم البيع لمصنع الصهر)، ويقيد ثمن البيع في إيرادات وأرباح المنشأة فوراً.")

    doc.add_page_break()

    # ══════════════════════════════════════════════════════════════
    # الفصل السادس: حسابات العملاء والديون والتحصيل
    # ══════════════════════════════════════════════════════════════
    doc.add_h1("الفصل السادس: حسابات العملاء والآجل والتحصيل", icon="💰")
    doc.add_p("إدارة الديون ومتابعة مستحقات المحل لدى العملاء والشركات بنظام الرصيد المفتوح وكشوف الحساب التفصيلية.")

    doc.add_h2("أولاً: استعراض مديونيات العملاء وكشف الحساب", icon="📋")
    doc.add_p("من القائمة الجانبية: اختر قسم المبيعات ⬅️ ثم اضغط على 'حسابات ومديونيات الآجل':")
    doc.add_bullet("نظرة عامة سريعة", "بتشوف جدول كامل يوضح اسم كل عميل، إجمالي مديونيته الحالية، سقف ائتمانه المسموح، وتاريخ آخر سداد.", icon="👁️")
    doc.add_bullet("طباعة كشف حساب تفصيلي", "بالضغط على زر 'كشف حساب' بجانب اسم العميل، بيظهر جدول رسمي بكل الفواتير والدفعات مع الرصيد قبل وبعد كل حركة بالتاريخ والدقيقة.", icon="🖨️")

    doc.add_h2("ثانياً: خطوات تحصيل دفعة نقدية وسداد مديونية", icon="💵")
    doc.add_step(1, "بدء عملية السداد", "اضغط زر 'تحصيل دفعة' الموجود بجوار اسم العميل في الجدول.")
    doc.add_step(2, "إدخال المبلغ المحصل", "اكتب المبلغ المسدد (النظام يمنع إدخال مبلغ أكبر من مديونية العميل الفعلية تلقائياً لمنع الأخطاء).")
    doc.add_step(3, "طريقة الدفع وبيانات الإيصال", "اختر طريقة السداد (نقداً بالخزينة أو تحويل بنكي/محفظة) واكتب رقم الإيصال إن وجد.")
    doc.add_step(4, "الحفظ وإصدار إيصال الاستلام", "اضغط زر 'حفظ سند القبض'. مديونية العميل تنخفض فوراً، ويتولد قيد قبض بالخزينة، وتقدر تطبع للعميل وصل استلام رسمي.")

    doc.add_page_break()

    # ══════════════════════════════════════════════════════════════
    # الفصل السابع: الموارد البشرية وشؤون الموظفين والبصمة
    # ══════════════════════════════════════════════════════════════
    doc.add_h1("الفصل السابع: شؤون الموظفين والبصمة والجزاءات التلقائية", icon="⏰")
    doc.add_p("النظام بيضبط مواعيد عمل الورشة والمعرض بدقة، وبيربط البصمات اليومية بحسابات الرواتب أوتوماتيكياً وبدون أي تدخل يدوي مشبوه.")

    doc.add_h2("أولاً: خطوات إضافة موظف وتحديد مواعيد الشفت والراتب", icon="👤")
    doc.add_step(1, "الدخول على شاشة الموظفين", "من القائمة الجانبية: اختر قسم الموارد البشرية ⬅️ ثم اضغط على 'سجل الموظفين' ⬅️ 'إضافة موظف جديد'.")
    doc.add_step(2, "البيانات الأساسية وتفاصيل الشفت", "اكتب الاسم الكامل، كود الموظف، رقم الهاتف، والمسمى الوظيفي. حدد بداية الشفت (مثلاً 09:00 صباحاً) ونهايته (05:00 مساءً) وفترة السماح (مثلاً 15 دقيقة).")
    doc.add_step(3, "تحديد هيكل الراتب والبدلات", "اكتب الراتب الأساسي الشهري، والبدلات الثابتة (مثل بدل سكن أو بدل انتقال).")

    doc.add_h2("ثانياً: القواعد الصارمة لمحرك الحضور والبصمة", icon="🔒")
    doc.add_p("سواء تم تسجيل البصمة يدوياً من الشاشة أو من خلال ربط جهاز البصمة، النظام بيطبق القواعد التالية تلقائياً:")
    doc.add_bullet("قاعدة الـ 5 دقائق لمنع التكرار (Debounce)", "لو الموظف بصم مرتين ورا بعض في أقل من 5 دقائق (لأنه اتلخبط أو شك إن البصمة ما سجلتش)، النظام بيتجاهل البصمة الثانية ويحافظ على الأولى.", icon="⏳")
    doc.add_bullet("منع تكرار تسجيل الحضور", "لو الموظف سجل حضور صباحاً وحاول يسجل حضور مرة ثانية في نفس اليوم، النظام بيرفض فوراً ويعلمه بأنه مسجل حضور بالفعل في ساعة معينة.", icon="🚫")
    doc.add_bullet("حساب دقائق التأخير بعد فترة السماح", "إذا بدأ الشفت 9:00 صباحاً ومعاه 15 دقيقة سماح وبصم 9:45 صباحاً، النظام بيسجل 45 دقيقة تأخير ويكتب حالته (متأخر).", icon="⏱️")

    doc.add_callout(
        "إذا تجاوز التأخير 30 دقيقة، النظام بينشئ مسودة جزاء آلي بقيمة ربع يوم عمل: (الراتب الأساسي ÷ 30) × 0.25. المشرف يدخل على شاشة 'الجزاءات والخصومات' لمراجعتها واعتمادها لتخصم من الراتب الشهري تلقائياً.",
        title="قانون الجزاء الآلي عند التأخير:",
        bg_color="FFF5F5",
        border_color="E53E3E",
        icon="⚠️"
    )

    doc.add_page_break()

    # ══════════════════════════════════════════════════════════════
    # الفصل الثامن: مسير الرواتب الشهري وحساب المستحقات
    # ══════════════════════════════════════════════════════════════
    doc.add_h1("الفصل الثامن: مسير الرواتب الشهري وقفل الحسابات", icon="💵")
    doc.add_p("نهاية كل شهر ميلادي، بضغطة زرار واحدة النظام بيجمع كل شغل الورشة ومبيعاتها والغياب والجزاءات ويخرج مسير الرواتب الصافي.")

    doc.add_h2("أولاً: خطوات توليد مسير الرواتب الشهرية", icon="📑")
    doc.add_step(1, "الدخول على شاشة الرواتب", "من القائمة الجانبية: اختر قسم الموارد البشرية ⬅️ ثم اضغط على 'مسيرات الرواتب الشهرية'.")
    doc.add_step(2, "تحديد الفرع والشهر المستهدف", "اختر فرع دمياط الجديدة، ثم حدد الشهر والسنة، واضغط زر 'توليد مسير الرواتب'.")
    doc.add_step(3, "مراجعة كشف الرواتب المبدئي", "النظام هيعرض جدولاً مفصلاً يوضح لكل موظف: الراتب الأساسي، البدلات، العمولات المستحقة، العمل الإضافي، خصم الغياب، الجزاءات المعتمدة، وصافي الراتب المستحق للصرف.")

    doc.add_h2("ثانياً: فك شفرة معادلة الراتب الصافي", icon="🧮")
    doc.add_p("النظام بيطبق المعادلة المحاسبية الصارمة التالية:")
    doc.add_p("صافي الراتب = (الأساسي + البدلات + الإضافي) - (تكلفة الغياب + الجزاءات المعتمدة)", bold=True, color="1B365D", size=24)
    doc.add_bullet("إلغاء عمولة التركيب الفردية", "بناءً على تعليمات الإدارة تم إلغاء عمولة الـ 25 جنيه عن تركيب البطاريات، مع إلزام تسجيل وتوثيق اسم الفني القائم بالتركيب على كل فاتورة لضبط الجودة ومتابعة الأداء.", icon="🔧")
    doc.add_bullet("خصم الغياب غير المبرر", "معيار العمل الشهري المعتمد 26 يوم عمل؛ أي يوم غياب ينقصه الموظف بدون إجازة رسمية معتمدة يخصم قيمته اليومية بالكامل.", icon="📅")

    doc.add_h2("ثالثاً: دورة حياة المسير وقفل الصرف المالي", icon="🔐")
    doc.add_bullet("المرحلة 1: مسودة (Draft)", "المسير بيبقى مسودة قابلة للمراجعة والتعديل إذا وردت بصمات متأخرة أو إجازات.", icon="1️⃣")
    doc.add_bullet("المرحلة 2: الاعتماد المالي (Approved)", "المدير المسؤول يضغط 'اعتماد المسير'، والنظام يسجل اسم المعتمد وتاريخ الاعتماد الدقيق.", icon="2️⃣")
    doc.add_bullet("المرحلة 3: الصرف والإغلاق التام (Disbursed)", "بعد تسليم المستحقات، يتم الضغط على 'صرف وإغلاق المسير'. هنا النظام يقفل الشهر ده تماماً ويمنع أي تعديل أو إعادة احتساب لمنع أي تلاعب مالي بعد الصرف.", icon="3️⃣")

    doc.add_page_break()

    # ══════════════════════════════════════════════════════════════
    # الفصل التاسع: البحث الشامل الفوري (Spotlight Search)
    # ══════════════════════════════════════════════════════════════
    doc.add_h1("الفصل التاسع: محرك البحث الشامل واختصار الكيبورد السريع", icon="🔍")
    doc.add_p("مش هتحتاج تدور في الجداول اليدوية؛ من أي مكان في النظام تقدر توصل لأي معلومة في أقل من ثانية واحدة.")

    doc.add_h2("أولاً: إزاي تفتح نافذة البحث السريع؟", icon="⌨️")
    doc.add_p("من أي شاشة داخل النظام، اضغط من لوحة المفاتيح على الاختصار التالي:")
    doc.add_p("Ctrl + K   (أو Cmd + K إذا كنت تستخدم جهاز Mac)", bold=True, color="2B6CB0", size=26)
    doc.add_p("ستظهر فوراً نافذة بحث منبثقة وذكية في منتصف الشاشة.")

    doc.add_h2("ثانياً: ميزة التطبيع العربي الذكي (Arabic Normalization)", icon="✨")
    doc.add_p("مش مهم كتبت الحروف بهمزة أو بدون همزة، النظام ذكي وهيعرف طلبك فوراً:")
    doc.add_bullet("الهمزات والألف", "لو كتبت 'احمد' هيطلعلك 'أحمد'، ولو كتبت 'ابراهيم' أو 'إبراهيم' هيجيبه بدون فرق.", icon="🔤")
    doc.add_bullet("التاء المربوطة والهاء", "لو كتبت 'فاطمه' هيطلع 'فاطمة'، ولو كتبت 'بطاريه' هتطلع 'بطارية'.", icon="🔤")
    doc.add_bullet("أرقام اللوحات والتليفونات", "لو كتبت رقم لوحة العربية 'ط س د 1234' بمسافات أو بدونها هيطلعلك سيارة العميل فوراً.", icon="🚗")

    doc.add_h2("ثالثاً: القطاعات التسعة المشمولة في البحث الفوري", icon="🎯")
    doc.add_p("بمجرد كتابة حرفين فقط، النظام بيبحث متزامناً وبشكل لحظي في:")
    doc.add_bullet("1. سجل الموظفين وفريق العمل", "بالاسم وكود الموظف ورقم التليفون والمسمى.", icon="👥")
    doc.add_bullet("2. قاعدة بيانات العملاء", "بالاسم ورقم التليفون والرقم القومي.", icon="🤝")
    doc.add_bullet("3. مركبات الورشة والعملاء", "بالماركة والموديل ورقم اللوحة ورقم الشاسيه.", icon="🚗")
    doc.add_bullet("4. البطاريات والمنتجات بالمخزن", "باسم الصنف، الماركة التجارية، والسعة بالأمبير.", icon="🔋")
    doc.add_bullet("5. فواتير المبيعات الصادرة", "برقم الفاتورة أو اسم المشتري.", icon="🧾")
    doc.add_bullet("6. شهادات الضمان الفعالة", "برقم السيريال الفريد وتاريخ الصلاحية.", icon="🛡️")
    doc.add_bullet("7. سجل الموردين والشركات", "باسم الشركة ورقم السجل والتليفون.", icon="🏢")
    doc.add_bullet("8. فواتير المشتريات والتوريد", "برقم فاتورة المورد ورقم الشحنة.", icon="📥")
    doc.add_bullet("9. تذاكر ومطالبات الضمان", "برقم المطالبة وسيريال البطارية المستبدلة.", icon="📋")

    doc.add_page_break()

    # ══════════════════════════════════════════════════════════════
    # الفصل العاشر: التقارير والطباعة وتصدير الإكسيل
    # ══════════════════════════════════════════════════════════════
    doc.add_h1("الفصل العاشر: التقارير والطباعة وتصدير الإكسيل بدون أخطاء", icon="📊")
    doc.add_p("شاشات التقارير بتديك الرؤية الشاملة عن أداء الورشة والمحل مع حماية تامة للغة العربية عند استخراج الملفات.")

    doc.add_h2("أولاً: تقارير الموارد البشرية والغياب والحضور", icon="📈")
    doc.add_p("من القائمة الجانبية: اختر قسم الموارد البشرية ⬅️ ثم اضغط على 'التقارير والإحصائيات':")
    doc.add_bullet("تحديد الفترات الزمنية", "تقدر تطلب تقرير يومي، تقرير شهري، أو فترة مخصصة من تاريخ محدد إلى تاريخ آخر.", icon="📅")
    doc.add_bullet("المخططات والرسوم البيانية", "رسوم تفاعلية بنسب الحضور والغياب والإجازات مع بيان تفصيلي لحالات التأخير.", icon="📊")
    doc.add_bullet("طباعة كارت الموظف المستقل", "عند فتح ملف أي موظف والضغط على زر 'طباعة البطاقة'، النظام بيطبع كارت الموظف كوثيقة رسمية نظيفة تماماً بدون أي أشرطة جانبية مشوشة.", icon="🖨️")

    doc.add_h2("ثانياً: ميزة تصدير Excel بترميز اللغة العربية السليم", icon="📗")
    doc.add_callout(
        "كتير من البرامج لما تصدر إكسيل بتفتح تلاقي الحروف العربية طالعة علامات استفهام ورموز غريبة ومشوهة. في نظام الحسيني تم دمج تقنية ترميز الحروف العربي المعتمد (UTF-8 BOM)؛ فلما تفتح الملف في Microsoft Excel بيفتح عربي مظبوط 100% ومنسق فوراً بدون أي حاجة لتعديل الإعدادات.",
        title="ميزة حصرية في تصدير ملفات Excel:",
        bg_color="F0FFF4",
        border_color="38A169",
        icon="✅"
    )

    doc.add_page_break()

    # ══════════════════════════════════════════════════════════════
    # الفصل الحادي عشر: فحص وتشخيص النظام والمحاكاة الحية
    # ══════════════════════════════════════════════════════════════
    doc.add_h1("الفصل الحادي عشر: فحص وتشخيص النظام والمحاكاة الذاتية", icon="🩺")
    doc.add_p("الأداة دي اتعملت عشان تطمنك في أي وقت إن النظام سليم 100% محاسبياً ومفيش أي رقم ضارب في قاعدة البيانات.")

    doc.add_h2("أولاً: شاشة الفحص الحي من لوحة التحكم", icon="🖥️")
    doc.add_p("من القائمة الجانبية: اختر قسم 'إدارة النظام والأدوات' ⬅️ ثم اضغط على 'شاشة الفحص والتشخيص الذاتي':")
    doc.add_bullet("مؤشر السلامة العامة لقاعدة البيانات", "بيفحص الجداول الحيوية (المخزون المتوفر، مديونيات العملاء، حسابات الموردين، شهادات الضمان، وتوازن مسير الرواتب) ويعرض نسبة سلامة البيانات بالأخضر التام (100%).", icon="🛡️")
    doc.add_bullet("زر المحاكاة الشاملة للعمليات", "بضغطة واحدة على زر 'تشغيل محاكاة شاملة للدورة الكاملة'، النظام بينفذ دورة عمل تجريبية وهمية في ثلث ثانية (شراء وتوريد بضاعة + بيع كاشير + خصم كهنة + دفع مجزأ + فحص ضمان واستبدال + بيع لوط رصاص + بصمة موظف وجزاء + تقفيل مسير راتب) ويتأكد إن كل الحسابات طلعت صحيحة ومطابقة للقرش، ثم يتراجع عن العمليات التجريبية فوراً للحفاظ على نظافة بيانات محلك!", icon="⚡")

    doc.add_page_break()

    # ══════════════════════════════════════════════════════════════
    # جدول الدليل التشغيلي السريع ومسارات الوصول للقوائم
    # ══════════════════════════════════════════════════════════════
    doc.add_h1("دليل مسارات الوصول السريع لجميع شاشات النظام", icon="🗺️")
    doc.add_p("الجدول ده بيوضح لكل موظف مكان الوصول لكل شاشة في القائمة الجانبية بدون الحاجة لأي روابط أو تعقيدات:")

    doc.add_table(
        ["الشاشة والوظيفة", "مكان الوصول في القائمة الجانبية", "الغرض والهدف التشغيلي للموظف"],
        [
            ["لوحة التحكم والمؤشرات", "أعلى القائمة الجانبية ⬅️ لوحة التحكم", "متابعة الإحصائيات العامة، مبيعات اليوم، وحالة الفروع"],
            ["كاشير نقطة البيع (POS)", "قسم المبيعات ⬅️ كاشير نقطة البيع", "إصدار فواتير البيع الفورية، تسجيل السيريال، وخصم الكهنة"],
            ["فواتير المبيعات الصادرة", "قسم المبيعات ⬅️ فواتير المبيعات", "أرشيف الفواتير السابقة، الطباعة، ومتابعة فواتير اليوم"],
            ["حسابات ومديونيات الآجل", "قسم المبيعات ⬅️ حسابات الآجل والديون", "متابعة ديون العملاء، سقف الائتمان، وتحصيل الدفعات النقدية"],
            ["دليل العملاء والمركبات", "قسم المبيعات ⬅️ العملاء والمركبات", "سجل بيانات العملاء وأرقام لوحات وشاسيهات السيارات"],
            ["فواتير التوريد والمشتريات", "قسم المشتريات ⬅️ فواتير التوريد", "تسجيل شحنات البطاريات الواردة وحساب التكلفة بالمتوسط المرجح"],
            ["دليل الموردين والشركات", "قسم المشتريات ⬅️ الموردون", "بيانات شركات ومصانع البطاريات وكشوف حساباتهم ومستحقاتهم"],
            ["فحص وسريان الضمان", "قسم الضمانات ⬅️ فحص الضمان", "الكشف اللحظي برقم السيريال وإجراء الاستبدال الفوري للبطارية"],
            ["سجل الضمانات والمطالبات", "قسم الضمانات ⬅️ سجل المطالبات", "أرشيف تذاكر الاستبدال ومتابعة تعويضات شركات التوريد"],
            ["مخزن بطاريات الكهنة", "قسم المخازن ⬅️ مخزن الكهنة", "متابعة رصيد البطاريات القديمة وأوزان الرصاص وبيع لوطات الصهر"],
            ["سجل الموظفين والورشة", "الموارد البشرية ⬅️ الموظفون", "إضافة وتعديل بيانات العاملين ومواعيد شفتاتهم وهيكل الراتب"],
            ["الحضور والانصراف اليومي", "الموارد البشرية ⬅️ الحضور والانصراف", "تسجيل ومتابعة البصمات اليومية، والتأخيرات، وفلتر منع التكرار"],
            ["الإجازات والطلبات", "الموارد البشرية ⬅️ طلبات الإجازات", "تسجيل واعتماد الإجازات الاعتيادية والمرضية وتأثيرها"],
            ["الجزاءات والخصومات", "الموارد البشرية ⬅️ الجزاءات والخصومات", "مراجعة واعتماد جزاءات التأخير التلقائية وخصومات الشهر"],
            ["مسيرات الرواتب الشهرية", "الموارد البشرية ⬅️ مسيرات الرواتب", "توليد واعتماد وإغلاق كشوف أجور الموظفين والعمولات والخصم"],
            ["تقارير الأداء والعمل", "الموارد البشرية ⬅️ التقارير", "التقارير اليومية والشهرية التفصيلية وطباعة كروت الموظفين"],
            ["الفحص والتشخيص الذاتي", "إدارة النظام ⬅️ الفحص والتشخيص", "فحص سلامة قاعدة البيانات وتشغيل المحاكاة الشاملة للعمليات"],
            ["إعدادات المنشأة والفروع", "إدارة النظام ⬅️ الإعدادات العامة", "تعديل بيانات المحل، اللوجو، وكود تفويض استثناءات الائتمان"],
        ]
    )

    doc.add_p("──────────────────────────────────────────────────────────", align="center", color="CBD5E0", size=20, space_before=240, space_after=120)
    doc.add_p("تم بحمد الله إعداد هذا الدليل الشامل ليكون المرجع والكتالوج اليومي المعتمد لتشغيل كافة أقسام منظومة مجموعة الحسيني.", align="center", bold=True, color="1B365D", size=24)

    doc.save()

if __name__ == "__main__":
    base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    targets = [
        os.path.join(base_dir, "Al_Husseini_Master_Manual.docx"),
        os.path.join(base_dir, "دليل_نظام_الحسيني_الشامل_المنسق.docx"),
        os.path.join(base_dir, "دليل_نظام_الحسيني_الشامل.docx"),
        os.path.join(base_dir, "docs", "دليل_نظام_الحسيني_الشامل.docx"),
    ]
    for target in targets:
        try:
            build_alhusseini_master_manual(target)
        except PermissionError:
            print("Notice: File is currently open in Word (locked). Skipped.")
        except Exception as e:
            print(f"Error writing file: {e}")


