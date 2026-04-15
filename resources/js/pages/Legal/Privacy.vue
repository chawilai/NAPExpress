<script setup lang="ts">
import LegalLayout from '@/layouts/LegalLayout.vue';

const toc = [
    { id: 'summary', label: 'สรุปสั้น' },
    { id: 'about', label: '1. ข้อมูลของเรา' },
    { id: 'data-we-collect', label: '2. ข้อมูลที่เราเก็บ' },
    { id: 'thaid', label: '3. Login ผ่าน ThaiID' },
    { id: 'purpose', label: '4. วัตถุประสงค์' },
    { id: 'never', label: '5. เราไม่ทำอะไร' },
    { id: 'sharing', label: '6. การแชร์ข้อมูล' },
    { id: 'localization', label: '7. ที่ตั้งข้อมูล' },
    { id: 'security', label: '8. ความปลอดภัย' },
    { id: 'rights', label: '9. สิทธิ์ของท่าน' },
    { id: 'cookies', label: '10. Cookies' },
    { id: 'contact', label: '11. ติดต่อเรา' },
];
</script>

<template>
    <LegalLayout
        title="นโยบายความเป็นส่วนตัว"
        subtitle="Privacy Policy — AutoNAP ให้ความสำคัญกับความเป็นส่วนตัวของผู้รับบริการและลูกค้า"
        effective-date="15 เมษายน 2569"
        version="1.0"
        :toc="toc"
    >
        <!-- TL;DR -->
        <section id="summary">
            <div class="callout callout-teal">
                <h3 class="mt-0 mb-2 text-lg font-bold">🎯 สรุปสั้น (TL;DR)</h3>
                <p class="mb-3">
                    AutoNAP <strong>ไม่เก็บข้อมูลผู้รับบริการของคลินิก</strong> ที่ท่าน upload มาประมวลผล เก็บไว้เฉพาะ:
                </p>
                <ul class="mb-3">
                    <li>✅ <strong>เลข 4 ตัวท้าย</strong>ของเลขประจำตัวประชาชน (xxxx1234)</li>
                    <li>✅ <strong>รหัส NAP</strong> ที่ได้จากระบบ สปสช.</li>
                    <li>✅ <strong>เวลาและสถานะ</strong>การประมวลผล</li>
                </ul>
                <p class="mb-0">
                    <strong>ไม่เก็บ:</strong> ชื่อ-นามสกุล, เบอร์โทร, ที่อยู่, อีเมล, ข้อมูลการแพทย์<br />
                    ข้อมูล audit ข้างต้นถูก<strong>ลบอัตโนมัติหลัง 90 วัน</strong>
                </p>
            </div>
        </section>

        <!-- 1. About -->
        <section id="about">
            <h2>1. ข้อมูลของเรา</h2>

            <h3>1.1 ผู้ให้บริการ</h3>
            <p>
                <strong>AutoNAP</strong> เป็นบริการ automation ที่ช่วยให้หน่วยบริการสุขภาพในเครือข่าย สปสช.
                สามารถบันทึกข้อมูลเข้าระบบ NAP Plus ได้อย่างอัตโนมัติ
            </p>
            <ul>
                <li><strong>ชื่อนิติบุคคล:</strong> [จะระบุเมื่อทางการ]</li>
                <li><strong>ที่ตั้ง:</strong> ประเทศไทย</li>
                <li><strong>อีเมลติดต่อ:</strong> <a href="mailto:privacy@autonap.co.th">privacy@autonap.co.th</a></li>
                <li><strong>DPO (Data Protection Officer):</strong> [จะระบุ]</li>
            </ul>

            <h3>1.2 บทบาทของเรา</h3>
            <ul>
                <li>
                    <strong>ในฐานะผู้ประมวลผล (Data Processor):</strong> สำหรับข้อมูลผู้รับบริการของคลินิก
                    (ปลายทางคือ NAP Plus)
                </li>
                <li>
                    <strong>ในฐานะผู้ควบคุมข้อมูล (Data Controller):</strong> สำหรับข้อมูลบัญชีและการใช้งานของลูกค้า
                    (คลินิก/มูลนิธิ) ที่สมัครใช้บริการ
                </li>
            </ul>
        </section>

        <!-- 2. Data we collect -->
        <section id="data-we-collect">
            <h2>2. ข้อมูลที่เราเก็บ</h2>

            <h3>2.1 ข้อมูลผู้รับบริการของคลินิก (End users ของคลินิก)</h3>
            <div class="callout callout-teal">
                <p class="m-0">
                    📌 <strong>หลักการสำคัญ:</strong> เราใช้ "Process-then-forget" — ประมวลผลแล้วลบทิ้ง
                </p>
            </div>

            <h4>🔄 ข้อมูลที่ประมวลผลชั่วคราว (In-memory เท่านั้น)</h4>
            <p>ในระหว่างการประมวลผลแต่ละ job เรา<strong>เข้าถึงข้อมูลเหล่านี้ชั่วคราว</strong>:</p>
            <ul>
                <li>เลขประจำตัวประชาชน 13 หลัก</li>
                <li>UIC (รหัสผู้รับบริการของคลินิก)</li>
                <li>วันที่ให้บริการ</li>
                <li>Key Population (KP)</li>
                <li>อาชีพ</li>
                <li>ข้อมูลอื่นในฟอร์ม NAP</li>
            </ul>
            <p>
                <strong>ข้อมูลเหล่านี้ถูกลบออกจากระบบทันทีหลังประมวลผลเสร็จสิ้น</strong> และไม่เก็บใน database
            </p>

            <h4>💾 ข้อมูลที่เก็บรักษา (Audit log)</h4>
            <p>เราเก็บเฉพาะข้อมูลต่อไปนี้เพื่อให้ท่านสามารถตรวจสอบย้อนหลังได้:</p>
            <table>
                <thead>
                    <tr>
                        <th>ฟิลด์</th>
                        <th>ตัวอย่าง</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>PID masked (4 ตัวท้าย)</td>
                        <td><code>xxxx1234</code></td>
                    </tr>
                    <tr>
                        <td>NAP code</td>
                        <td><code>RR-2569-123456</code></td>
                    </tr>
                    <tr>
                        <td>Form type</td>
                        <td><code>RR</code> / <code>VCT</code></td>
                    </tr>
                    <tr>
                        <td>Status</td>
                        <td><code>success</code> / <code>failed</code></td>
                    </tr>
                    <tr>
                        <td>Timestamp</td>
                        <td><code>2026-04-15 10:30:15</code></td>
                    </tr>
                </tbody>
            </table>
            <p>
                <strong>ระยะเวลาเก็บ: 90 วัน</strong> หลังจากนั้นระบบจะลบอัตโนมัติ
            </p>

            <h4>❌ ข้อมูลที่เราไม่เก็บ</h4>
            <ul>
                <li>ชื่อ-นามสกุล</li>
                <li>เลข PID เต็ม 13 หลัก</li>
                <li>เบอร์โทรศัพท์</li>
                <li>อีเมล</li>
                <li>ที่อยู่</li>
                <li>ประวัติการรักษา</li>
                <li>ผลตรวจทางการแพทย์รายละเอียด</li>
                <li>CSV/Excel ไฟล์ที่ upload (ลบหลังประมวลผล)</li>
            </ul>

            <h3>2.2 ข้อมูลบัญชีของลูกค้า (คลินิก/มูลนิธิที่สมัครใช้บริการ)</h3>
            <p>เพื่อให้บริการเราเก็บ:</p>
            <ul>
                <li>ชื่อคลินิก/มูลนิธิ</li>
                <li>ชื่อผู้ติดต่อ</li>
                <li>อีเมล</li>
                <li>เบอร์โทรศัพท์</li>
                <li>ข้อมูลสำหรับออกใบเสร็จ (ที่อยู่, เลขผู้เสียภาษี)</li>
                <li>ข้อมูลการเข้าใช้งานระบบ (login log)</li>
            </ul>
            <p><strong>ระยะเวลาเก็บ:</strong> ตลอดระยะเวลาสัญญา + 5 ปี ตามกฎหมายภาษี</p>
        </section>

        <!-- 3. ThaiID -->
        <section id="thaid">
            <h2>3. Login ผ่าน ThaiID</h2>

            <h3>3.1 อย่างไร</h3>
            <p>
                AutoNAP รองรับการ login เข้าระบบ NAP Plus ผ่าน <strong>ThaiID</strong>
                ซึ่งเป็นระบบยืนยันตัวตนอย่างเป็นทางการของรัฐ
            </p>

            <h3>3.2 ประโยชน์ด้านความปลอดภัย</h3>
            <ul>
                <li>✅ เราไม่เก็บ password ของท่าน</li>
                <li>✅ ผู้ใช้ยืนยันตัวตนและอนุญาตการเข้าใช้งาน<strong>ทุกครั้ง</strong></li>
                <li>✅ ใช้ระบบของรัฐที่มีมาตรฐาน security สูง</li>
                <li>✅ สามารถยกเลิกการอนุญาตได้เมื่อต้องการ</li>
            </ul>

            <h3>3.3 AutoNAP ไม่ทำ</h3>
            <ul>
                <li>❌ ไม่เก็บ ThaiID credentials</li>
                <li>❌ ไม่ดึงข้อมูลส่วนตัวจาก ThaiID นอกจากที่จำเป็นต่อการ login</li>
                <li>❌ ไม่ใช้ ThaiID เพื่อวัตถุประสงค์อื่น</li>
            </ul>
        </section>

        <!-- 4. Purpose -->
        <section id="purpose">
            <h2>4. วัตถุประสงค์การใช้ข้อมูล</h2>
            <p>เราใช้ข้อมูลเพื่อวัตถุประสงค์ต่อไปนี้เท่านั้น:</p>

            <h3>4.1 ข้อมูลผู้รับบริการ</h3>
            <ul>
                <li><strong>วัตถุประสงค์เดียว:</strong> บันทึกเข้าระบบ NAP Plus ของ สปสช. ตามภารกิจของคลินิก</li>
                <li><strong>ระยะเวลา:</strong> ในขณะประมวลผลเท่านั้น (ข้อมูลเต็ม) + 90 วัน (audit log)</li>
            </ul>

            <h3>4.2 ข้อมูลบัญชีลูกค้า</h3>
            <ul>
                <li>ให้บริการและจัดการบัญชี</li>
                <li>ออกใบเสร็จและใบแจ้งหนี้</li>
                <li>สนับสนุนลูกค้า (customer support)</li>
                <li>แจ้งข่าวสารที่เกี่ยวข้องกับบริการ (เช่น update, maintenance)</li>
            </ul>
        </section>

        <!-- 5. Never -->
        <section id="never">
            <h2>5. เราไม่ทำอะไรกับข้อมูล</h2>
            <div class="callout callout-amber">
                <p class="m-0">
                    <strong>✋ เราสัญญาว่าจะไม่:</strong>
                </p>
            </div>
            <ul>
                <li>❌ ขายข้อมูลให้บุคคลที่สามใดๆ</li>
                <li>❌ ใช้ข้อมูลเพื่อการตลาด (นอกจากแจ้งข่าว product แก่ลูกค้าเอง)</li>
                <li>❌ ใช้ข้อมูลผู้รับบริการเพื่อ train AI model</li>
                <li>❌ วิเคราะห์ข้อมูลเชิงลึกเพื่อ benchmark หรือเผยแพร่</li>
                <li>❌ โอนข้อมูลออกนอกประเทศไทยโดยไม่ได้รับความยินยอม</li>
                <li>❌ แชร์ข้อมูลให้บริษัทในเครือหรือ partner</li>
                <li>❌ ประมวลผลข้อมูลเกินกว่าวัตถุประสงค์ที่ระบุไว้</li>
            </ul>
        </section>

        <!-- 6. Sharing -->
        <section id="sharing">
            <h2>6. การแชร์ข้อมูลกับบุคคลที่สาม</h2>
            <p>เราแชร์ข้อมูลเฉพาะในกรณีต่อไปนี้เท่านั้น:</p>

            <h3>6.1 NHSO (สปสช.)</h3>
            <p>
                เราส่งข้อมูลเคสเข้าระบบ <strong>NAP Plus ของ สปสช.</strong> — นี่คือวัตถุประสงค์หลักของบริการ
            </p>

            <h3>6.2 Sub-Processors</h3>
            <p>เราใช้ผู้ให้บริการ infrastructure ที่จำเป็น (ทั้งหมดตั้งอยู่ในประเทศไทย หรือเฉพาะ metadata ที่ไม่มี PII):</p>
            <table>
                <thead>
                    <tr>
                        <th>ผู้ให้บริการ</th>
                        <th>หน้าที่</th>
                        <th>ที่ตั้งข้อมูล</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>VPS provider</td>
                        <td>Server hosting</td>
                        <td>ไทย</td>
                    </tr>
                    <tr>
                        <td>Email service (SMTP)</td>
                        <td>ส่ง report</td>
                        <td>ไทย/ASEAN</td>
                    </tr>
                    <tr>
                        <td>Realtime service (Ably)</td>
                        <td>Progress updates</td>
                        <td>Global CDN (ไม่มี PII)</td>
                    </tr>
                </tbody>
            </table>

            <h3>6.3 หน่วยงานของรัฐ</h3>
            <p>
                เราอาจเปิดเผยข้อมูลตามคำสั่งศาลหรือกฎหมาย — ในกรณีเช่นนั้น เราจะแจ้งท่านล่วงหน้าหากกฎหมายอนุญาต
            </p>
        </section>

        <!-- 7. Localization -->
        <section id="localization">
            <h2>7. ที่ตั้งของข้อมูล (Data Localization)</h2>
            <p>
                ข้อมูลทั้งหมดของ AutoNAP <strong>จัดเก็บบน server ในประเทศไทย</strong>
                เราไม่ถ่ายโอนข้อมูลออกนอกประเทศโดยไม่ได้รับความยินยอม
            </p>
            <p>
                ข้อยกเว้น: metadata เล็กน้อยของ realtime service (Ably) อาจผ่าน CDN ระดับโลก
                แต่เป็นข้อมูลที่<strong>ไม่มี PII</strong> (เช่น progress percentage, status code)
            </p>
        </section>

        <!-- 8. Security -->
        <section id="security">
            <h2>8. มาตรการรักษาความปลอดภัย</h2>
            <p>เราใช้มาตรการทางเทคนิคและองค์กรเพื่อปกป้องข้อมูล:</p>

            <h3>เทคนิค</h3>
            <ul>
                <li>เข้ารหัสข้อมูลระหว่างส่ง (TLS 1.2+)</li>
                <li>เข้ารหัสข้อมูลที่เก็บรักษา (AES-256)</li>
                <li>Access control แบบ role-based</li>
                <li>Audit log สำหรับทุกการเข้าถึงข้อมูล</li>
                <li>Backup + disaster recovery</li>
                <li>Regular security patching</li>
            </ul>

            <h3>องค์กร</h3>
            <ul>
                <li>NDA กับพนักงานทุกคน</li>
                <li>PDPA training ประจำปี</li>
                <li>Least-privilege access policy</li>
                <li>Incident response plan</li>
            </ul>
        </section>

        <!-- 9. Rights -->
        <section id="rights">
            <h2>9. สิทธิ์ของเจ้าของข้อมูล (Data Subject Rights)</h2>
            <p>ตาม PDPA มาตรา 30-37 ท่านมีสิทธิ์ดังต่อไปนี้:</p>
            <table>
                <thead>
                    <tr>
                        <th>สิทธิ์</th>
                        <th>รายละเอียด</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Right of Access</strong></td>
                        <td>ขอดูข้อมูลที่เรามี</td>
                    </tr>
                    <tr>
                        <td><strong>Right to Rectification</strong></td>
                        <td>ขอแก้ไขข้อมูลที่ผิด</td>
                    </tr>
                    <tr>
                        <td><strong>Right to Erasure</strong></td>
                        <td>ขอให้ลบข้อมูล (เราลบภายใน 7 วันทำการ)</td>
                    </tr>
                    <tr>
                        <td><strong>Right to Restrict</strong></td>
                        <td>ขอระงับการประมวลผล</td>
                    </tr>
                    <tr>
                        <td><strong>Right to Data Portability</strong></td>
                        <td>ขอ export ข้อมูลในรูปแบบ CSV/JSON</td>
                    </tr>
                    <tr>
                        <td><strong>Right to Object</strong></td>
                        <td>คัดค้านการประมวลผล</td>
                    </tr>
                    <tr>
                        <td><strong>Right to Withdraw Consent</strong></td>
                        <td>ถอนความยินยอม (ยกเลิกบัญชีได้ทุกเมื่อ)</td>
                    </tr>
                    <tr>
                        <td><strong>Right to Complain</strong></td>
                        <td>ร้องเรียนต่อ PDPC ได้ที่ <a href="https://www.pdpc.or.th" target="_blank" rel="noopener">pdpc.or.th</a></td>
                    </tr>
                </tbody>
            </table>
            <p>
                ติดต่อเพื่อใช้สิทธิ์ได้ที่ <a href="mailto:privacy@autonap.co.th">privacy@autonap.co.th</a>
            </p>
        </section>

        <!-- 10. Cookies -->
        <section id="cookies">
            <h2>10. Cookies และ Tracking</h2>

            <h3>10.1 Cookies ที่เราใช้</h3>
            <ul>
                <li>
                    <strong>Essential cookies:</strong> จำเป็นสำหรับการทำงานของระบบ (session, authentication)
                    — ไม่ต้องขอความยินยอม
                </li>
                <li>
                    <strong>Analytics cookies:</strong> (optional) เก็บสถิติการใช้งาน อาจใช้ Plausible
                    (privacy-friendly, ไม่มี PII) — ขอความยินยอมก่อนใช้
                </li>
            </ul>

            <h3>10.2 เราไม่ใช้</h3>
            <ul>
                <li>❌ Google Analytics (GA4)</li>
                <li>❌ Facebook Pixel</li>
                <li>❌ Third-party advertising cookies</li>
                <li>❌ Cross-site tracking</li>
            </ul>
        </section>

        <!-- 11. Contact -->
        <section id="contact">
            <h2>11. ติดต่อเรา</h2>
            <p>หากมีคำถามเกี่ยวกับนโยบายความเป็นส่วนตัวนี้:</p>
            <ul>
                <li>
                    <strong>อีเมล (ทั่วไป):</strong>
                    <a href="mailto:privacy@autonap.co.th">privacy@autonap.co.th</a>
                </li>
                <li>
                    <strong>อีเมล (รายงานเหตุการณ์):</strong>
                    <a href="mailto:incident@autonap.co.th">incident@autonap.co.th</a>
                </li>
                <li><strong>DPO:</strong> [จะระบุเมื่อทางการ]</li>
                <li><strong>ที่อยู่:</strong> [จะระบุเมื่อทางการ]</li>
            </ul>

            <h3>การร้องเรียน</h3>
            <p>หากท่านไม่พอใจกับวิธีที่เราประมวลผลข้อมูล ท่านสามารถร้องเรียนต่อ:</p>
            <div class="callout callout-teal">
                <p class="m-0">
                    <strong>สำนักงานคณะกรรมการคุ้มครองข้อมูลส่วนบุคคล (PDPC)</strong><br />
                    เว็บไซต์: <a href="https://www.pdpc.or.th" target="_blank" rel="noopener">pdpc.or.th</a><br />
                    อีเมล: saraban@pdpc.or.th<br />
                    โทรศัพท์: 02-142-1033
                </p>
            </div>

            <hr class="my-10 border-slate-200 dark:border-slate-800" />

            <p class="text-sm text-slate-500 italic">
                ✍️ โดยการใช้บริการ AutoNAP ท่านรับทราบและยอมรับนโยบายความเป็นส่วนตัวนี้
            </p>
        </section>
    </LegalLayout>
</template>
