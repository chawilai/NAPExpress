<script setup lang="ts">
import LegalLayout from '@/layouts/LegalLayout.vue';

const toc = [
    { id: 'summary', label: 'สรุปสั้น' },
    { id: 'acceptance', label: '1. การยอมรับข้อตกลง' },
    { id: 'service', label: '2. บริการ' },
    { id: 'account', label: '3. บัญชีผู้ใช้' },
    { id: 'acceptable-use', label: '4. การใช้งานที่ยอมรับได้' },
    { id: 'payment', label: '5. ค่าบริการและการชำระเงิน' },
    { id: 'trial', label: '6. ทดลองใช้ฟรี' },
    { id: 'data', label: '7. ข้อมูลและความเป็นส่วนตัว' },
    { id: 'ip', label: '8. ทรัพย์สินทางปัญญา' },
    { id: 'warranty', label: '9. ข้อจำกัดความรับประกัน' },
    { id: 'liability', label: '10. ขอบเขตความรับผิด' },
    { id: 'termination', label: '11. การยกเลิกสัญญา' },
    { id: 'changes', label: '12. การเปลี่ยนแปลง' },
    { id: 'law', label: '13. กฎหมายที่ใช้บังคับ' },
    { id: 'contact', label: '14. ติดต่อ' },
];
</script>

<template>
    <LegalLayout
        title="ข้อตกลงการใช้บริการ"
        subtitle="Terms of Service — เงื่อนไขในการใช้บริการ AutoNAP"
        effective-date="15 เมษายน 2569"
        version="1.0"
        :toc="toc"
    >
        <!-- Summary -->
        <section id="summary">
            <div class="callout callout-teal">
                <h3 class="mt-0 mb-2 text-lg font-bold">📋 สรุปสั้น</h3>
                <ul class="mb-0">
                    <li>AutoNAP เป็นบริการ automation สำหรับบันทึกข้อมูลเข้าระบบ NAP Plus ของ สปสช.</li>
                    <li>ลูกค้ารับผิดชอบความถูกต้องของข้อมูลและการใช้งานภายใต้ scope ที่ สปสช. กำหนด</li>
                    <li>ทดลองใช้ฟรี 30 วัน ยกเลิกได้ทุกเมื่อ</li>
                    <li>ไม่ผูกมัดสัญญา ยกเว้นช่วง Founding 50 ที่ล็อคราคา 12 เดือน</li>
                    <li>ความรับผิดของเราจำกัดที่ค่าบริการ 12 เดือน</li>
                </ul>
            </div>
            <p class="text-sm text-slate-500 italic">
                ⚖️ เอกสารนี้เป็นฉบับ v1.0 อาจมีการปรับปรุงหลัง legal review ครั้งแรก
            </p>
        </section>

        <!-- 1. Acceptance -->
        <section id="acceptance">
            <h2>1. การยอมรับข้อตกลง</h2>
            <p>
                โดยการเข้าใช้บริการ AutoNAP (ต่อไปนี้เรียกว่า "บริการ") ท่าน (ต่อไปนี้เรียกว่า "ลูกค้า" หรือ "ท่าน")
                ตกลงและผูกพันตามข้อตกลงในเอกสารนี้ ("Terms of Service") รวมถึง
                <a href="/privacy">นโยบายความเป็นส่วนตัว</a> และเอกสารอื่นที่อ้างอิงในสัญญา
            </p>
            <p>
                หากท่านไม่เห็นด้วยกับข้อใดข้อหนึ่ง ขอให้ท่าน<strong>ไม่ใช้บริการ</strong>
            </p>
            <p>
                ท่านที่ลงทะเบียนในนามของนิติบุคคล (คลินิก, มูลนิธิ, สมาคม) รับรองว่าท่านมีอำนาจในการผูกพันนิติบุคคลนั้น
            </p>
        </section>

        <!-- 2. Service -->
        <section id="service">
            <h2>2. บริการ</h2>

            <h3>2.1 ขอบเขตของบริการ</h3>
            <p>AutoNAP ให้บริการ:</p>
            <ul>
                <li><strong>Automation การบันทึก NAP:</strong> รับข้อมูลจากลูกค้าและบันทึกเข้าระบบ NAP Plus ของ สปสช. โดยอัตโนมัติ</li>
                <li><strong>Dashboard:</strong> แสดงสถานะ job แบบ real-time และ audit log</li>
                <li><strong>Email report:</strong> รายงานผลการบันทึกทุกครั้งที่ job เสร็จ</li>
                <li><strong>Callback API:</strong> ส่งผลลัพธ์กลับระบบของลูกค้า (ถ้ามี)</li>
                <li><strong>CSV/API import:</strong> รับข้อมูลผ่านหลายช่องทาง</li>
            </ul>

            <h3>2.2 สิ่งที่บริการไม่ครอบคลุม</h3>
            <ul>
                <li>การแก้ไข/ลบข้อมูลที่อยู่ในระบบ สปสช. แล้ว</li>
                <li>การเข้าถึงข้อมูลของหน่วยบริการอื่น</li>
                <li>การรับประกันว่า สปสช. จะอนุมัติเคลม</li>
                <li>การให้คำปรึกษาทางการแพทย์หรือทางกฎหมาย</li>
            </ul>
        </section>

        <!-- 3. Account -->
        <section id="account">
            <h2>3. บัญชีผู้ใช้</h2>

            <h3>3.1 คุณสมบัติ</h3>
            <p>บริการสำหรับ:</p>
            <ul>
                <li>หน่วยบริการสุขภาพที่ขึ้นทะเบียนกับ สปสช.</li>
                <li>มูลนิธิ / สมาคม / ชมรมที่ทำงานด้าน HIV/STI หรือสาธารณสุขชุมชน</li>
                <li>บุคลากรทางการแพทย์ / เจ้าหน้าที่สาธารณสุขที่ได้รับมอบหมายจากหน่วยบริการ</li>
            </ul>

            <h3>3.2 ความรับผิดชอบในบัญชี</h3>
            <ul>
                <li>ลูกค้ารับผิดชอบรักษาความลับของ credentials ของตัวเอง</li>
                <li>ลูกค้ารับผิดชอบทุก activity ที่เกิดจากบัญชีของตัวเอง</li>
                <li>แจ้งเราทันทีหากพบว่าบัญชีถูกใช้งานโดยไม่ได้รับอนุญาต</li>
            </ul>
        </section>

        <!-- 4. Acceptable Use -->
        <section id="acceptable-use">
            <h2>4. การใช้งานที่ยอมรับได้</h2>

            <h3>4.1 ลูกค้าต้อง</h3>
            <ul>
                <li>✅ ใช้บริการเพื่อวัตถุประสงค์ที่ชอบด้วยกฎหมาย</li>
                <li>✅ ส่งข้อมูลที่ถูกต้อง ตรงความจริง และได้รับ consent จากผู้รับบริการ</li>
                <li>✅ ปฏิบัติตามกฎ สปสช. และ PDPA</li>
                <li>✅ ใช้ credentials ของตัวเอง (ห้าม share)</li>
                <li>✅ เคารพ rate limit ของระบบ</li>
            </ul>

            <h3>4.2 ลูกค้าต้องไม่</h3>
            <ul>
                <li>❌ ส่งข้อมูลปลอม/เท็จ/ฉ้อโกง</li>
                <li>❌ ใช้บริการเพื่อเคลมเงินที่ไม่ถูกต้องจาก สปสช.</li>
                <li>❌ ทำ reverse engineering / decompile / disassemble ระบบ</li>
                <li>❌ ขาย/ให้เช่า/แบ่งบัญชีให้บุคคลอื่น</li>
                <li>❌ ใช้เพื่อวิเคราะห์/benchmark บริการของผู้ให้บริการอื่น</li>
                <li>❌ อัพโหลดข้อมูลของคลินิกอื่นที่ไม่ได้รับอนุญาต</li>
                <li>❌ ก่อกวนการทำงานของระบบ (spam, DoS)</li>
            </ul>

            <h3>4.3 บทลงโทษ</h3>
            <p>
                หากพบการละเมิด เรามีสิทธิ์<strong>ระงับบัญชีทันที</strong>โดยไม่แจ้งล่วงหน้า
                และอาจดำเนินการตามกฎหมายหากทำให้เกิดความเสียหาย
            </p>
        </section>

        <!-- 5. Payment -->
        <section id="payment">
            <h2>5. ค่าบริการและการชำระเงิน</h2>

            <h3>5.1 โครงสร้างราคา</h3>
            <p>
                ค่าบริการอ้างอิงตามแพ็คเกจที่แสดงในหน้า <a href="/">Pricing</a> ในเว็บไซต์
                ราคาอาจปรับปรุงได้โดยแจ้งล่วงหน้า 30 วัน (ยกเว้นลูกค้าในช่วง Founding 50 ที่ล็อคราคา 12 เดือน)
            </p>

            <h3>5.2 การชำระเงิน</h3>
            <ul>
                <li>รายเดือน (default)</li>
                <li>รายปี (มีส่วนลด)</li>
                <li>จ่ายล่วงหน้าหลายเดือน (มี bonus)</li>
            </ul>

            <h3>5.3 ใบเสร็จและภาษี</h3>
            <ul>
                <li>ราคาที่แสดงยังไม่รวม VAT (ถ้ามี)</li>
                <li>ลูกค้าแจ้งข้อมูลเพื่อออกใบกำกับภาษี</li>
                <li>ภาษีหัก ณ ที่จ่าย (ถ้ามี) ลูกค้ารับผิดชอบ</li>
            </ul>

            <h3>5.4 การค้างชำระ</h3>
            <p>
                หากค้างชำระเกิน 14 วัน เราอาจระงับบริการชั่วคราว<br />
                หากค้างชำระเกิน 60 วัน เราอาจยกเลิกบัญชีและลบข้อมูล audit
            </p>
        </section>

        <!-- 6. Free Trial -->
        <section id="trial">
            <h2>6. ทดลองใช้ฟรี (Free Trial)</h2>
            <ul>
                <li>✅ ทดลองฟรี <strong>30 วัน</strong> ไม่ต้องใส่บัตรเครดิต</li>
                <li>✅ ใช้ฟีเจอร์ทั้งหมดของ Growth package</li>
                <li>✅ Quota 300 เคส (สำหรับ trial)</li>
                <li>✅ ไม่มีเงื่อนไขยกเลิก</li>
                <li>⚠️ หลัง 30 วันบัญชีจะอยู่ในสถานะ read-only จนกว่าจะ upgrade</li>
                <li>⚠️ 1 องค์กร = 1 trial</li>
            </ul>
        </section>

        <!-- 7. Data -->
        <section id="data">
            <h2>7. ข้อมูลและความเป็นส่วนตัว</h2>
            <p>
                การประมวลผลข้อมูลส่วนบุคคลอยู่ภายใต้
                <a href="/privacy">นโยบายความเป็นส่วนตัว</a>
                ซึ่งเป็นส่วนหนึ่งของข้อตกลงนี้
            </p>

            <h3>7.1 ข้อสัญญาหลัก</h3>
            <ul>
                <li><strong>Zero Data Retention:</strong> เราไม่เก็บข้อมูลผู้รับบริการหลังประมวลผลเสร็จ</li>
                <li><strong>Audit log:</strong> เก็บเฉพาะ masked PID + NAP code 90 วัน</li>
                <li><strong>Data localization:</strong> ข้อมูลอยู่ในประเทศไทย</li>
                <li><strong>DPA:</strong> ลูกค้าสามารถขอ Data Processing Agreement ได้ทุกเมื่อ</li>
            </ul>

            <h3>7.2 ความรับผิดชอบของลูกค้า</h3>
            <ul>
                <li>ลูกค้ารับผิดชอบการมี legal basis สำหรับการประมวลผลข้อมูลผู้รับบริการ</li>
                <li>ลูกค้ารับผิดชอบ privacy notice ที่ให้กับผู้รับบริการ</li>
                <li>ลูกค้ารับผิดชอบความถูกต้องของข้อมูลที่ส่งมา</li>
            </ul>
        </section>

        <!-- 8. IP -->
        <section id="ip">
            <h2>8. ทรัพย์สินทางปัญญา</h2>

            <h3>8.1 Ownership</h3>
            <ul>
                <li><strong>AutoNAP เป็นเจ้าของ:</strong> source code, design, trademark, logo, documentation</li>
                <li><strong>ลูกค้าเป็นเจ้าของ:</strong> ข้อมูลของตัวเอง, content ที่ upload</li>
            </ul>

            <h3>8.2 License</h3>
            <p>
                เราให้ license ใช้บริการในลักษณะ <strong>non-exclusive, non-transferable, revocable</strong>
                ตามระยะเวลาที่ลูกค้าสมัครใช้
            </p>
        </section>

        <!-- 9. Warranty -->
        <section id="warranty">
            <h2>9. ข้อจำกัดความรับประกัน</h2>
            <div class="callout callout-amber">
                <p class="m-0">
                    บริการให้ "AS IS" — เราทำอย่างเต็มที่เพื่อความถูกต้องและความพร้อมใช้งาน
                    แต่ไม่รับประกัน:
                </p>
            </div>
            <ul>
                <li>❌ บริการจะไม่มี downtime 100%</li>
                <li>❌ สปสช. จะอนุมัติทุกเคสที่บันทึก</li>
                <li>❌ ข้อมูลที่ลูกค้าส่งมาถูกต้อง</li>
                <li>❌ การเปลี่ยนแปลงในระบบ สปสช. จะไม่กระทบบริการ</li>
                <li>❌ ผลลัพธ์ทางธุรกิจ (เช่น จำนวนเงินเคลมที่ได้)</li>
            </ul>
            <p>
                <strong>SLA (ถ้ามี):</strong> 99% uptime สำหรับ Scale/Enterprise package
                (วัดเฉพาะ AutoNAP ไม่รวม downtime ของ สปสช.)
            </p>
        </section>

        <!-- 10. Liability -->
        <section id="liability">
            <h2>10. ขอบเขตความรับผิด</h2>
            <p>
                <strong>ความรับผิดรวมของ AutoNAP ในหนึ่งปีไม่เกินจำนวนค่าบริการที่ได้รับจากลูกค้าในปีนั้น</strong>
            </p>
            <p>เราไม่รับผิดต่อความเสียหายทางอ้อม (indirect damages) เช่น:</p>
            <ul>
                <li>กำไรที่สูญเสีย (lost profits)</li>
                <li>ชื่อเสียงที่เสียหาย</li>
                <li>โอกาสทางธุรกิจที่หายไป</li>
                <li>ข้อมูลที่สูญหายจากการกระทำของบุคคลที่สาม</li>
            </ul>
            <p>
                ข้อจำกัดนี้<strong>ไม่ครอบคลุม</strong>การกระทำโดยเจตนา (wilful misconduct)
                หรือการละเมิด PDPA ที่เกิดจาก AutoNAP โดยตรง
            </p>
        </section>

        <!-- 11. Termination -->
        <section id="termination">
            <h2>11. การยกเลิกสัญญา</h2>

            <h3>11.1 ลูกค้ายกเลิกได้</h3>
            <ul>
                <li>ยกเลิกได้ทุกเมื่อผ่าน dashboard หรือ email</li>
                <li>ไม่มีค่าปรับ</li>
                <li>จ่ายค่าบริการจนถึงสิ้นสุดงวดปัจจุบัน</li>
                <li>ช่วง Founding 50: ยกเลิกได้ แต่ไม่คืนเงินที่จ่ายล่วงหน้า</li>
            </ul>

            <h3>11.2 AutoNAP ยกเลิกได้ในกรณี</h3>
            <ul>
                <li>ลูกค้าละเมิด Acceptable Use Policy</li>
                <li>ค้างชำระเกิน 60 วัน</li>
                <li>ใช้งานในทางที่อาจทำให้เราเสี่ยงทางกฎหมาย</li>
                <li>เราเลิกกิจการ (แจ้งล่วงหน้า 90 วัน)</li>
            </ul>

            <h3>11.3 หลังการยกเลิก</h3>
            <ul>
                <li>ข้อมูล audit log ถูกลบภายใน 30 วัน (หรือเร็วกว่าถ้าลูกค้าขอ)</li>
                <li>ลูกค้าสามารถ export ข้อมูลก่อนยกเลิกได้</li>
                <li>ใบเสร็จและเอกสารบัญชีเก็บตามกฎหมาย (5 ปี)</li>
            </ul>
        </section>

        <!-- 12. Changes -->
        <section id="changes">
            <h2>12. การเปลี่ยนแปลงข้อตกลง</h2>
            <ul>
                <li>เราอาจปรับปรุงข้อตกลงได้ โดยแจ้งลูกค้าล่วงหน้า <strong>30 วัน</strong> ทาง email + ประกาศบนเว็บไซต์</li>
                <li>หากลูกค้าไม่เห็นด้วย สามารถยกเลิกบริการโดยไม่เสียค่าปรับ</li>
                <li>การใช้บริการต่อหลังวัน Effective ถือเป็นการยอมรับข้อตกลงใหม่</li>
            </ul>
        </section>

        <!-- 13. Law -->
        <section id="law">
            <h2>13. กฎหมายที่ใช้บังคับ</h2>
            <p>ข้อตกลงนี้อยู่ภายใต้กฎหมายไทย</p>
            <p>
                ข้อพิพาทใด ๆ ให้อยู่ในเขตอำนาจของศาลในกรุงเทพมหานคร
                เว้นแต่คู่สัญญาตกลงใช้การอนุญาโตตุลาการ
            </p>
        </section>

        <!-- 14. Contact -->
        <section id="contact">
            <h2>14. ติดต่อเรา</h2>
            <ul>
                <li><strong>ทั่วไป:</strong> <a href="mailto:hello@autonap.co.th">hello@autonap.co.th</a></li>
                <li><strong>Support:</strong> <a href="mailto:support@autonap.co.th">support@autonap.co.th</a></li>
                <li><strong>Privacy:</strong> <a href="mailto:privacy@autonap.co.th">privacy@autonap.co.th</a></li>
                <li><strong>Billing:</strong> <a href="mailto:billing@autonap.co.th">billing@autonap.co.th</a></li>
            </ul>

            <hr class="my-10 border-slate-200 dark:border-slate-800" />

            <p class="text-sm text-slate-500 italic">
                📜 เอกสารนี้ยังไม่ได้ผ่านการ review จากที่ปรึกษากฎหมาย
                ควรได้รับการปรับปรุงก่อนใช้เป็นเอกสารทางการ
            </p>
        </section>
    </LegalLayout>
</template>
