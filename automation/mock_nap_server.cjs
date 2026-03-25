const http = require('http');
const url = require('url');
const querystring = require('querystring');

/**
 * Mock NAP Plus Server
 *
 * Mimics the real NAP Plus form structure for safe testing.
 * Supports the full 5-step DirectHTTP flow + Playwright browser automation.
 *
 * Usage: node automation/mock_nap_server.cjs [port]
 * Default: http://localhost:9999
 */

const PORT = parseInt(process.argv[2]) || 9999;

// Track session state per cookie
const sessions = new Map();
let rrCounter = 1000;

function getSession(req) {
    const cookies = (req.headers.cookie || '').split(';').reduce((acc, c) => {
        const [k, v] = c.trim().split('=');
        if (k) acc[k] = v;
        return acc;
    }, {});
    const sid = cookies['JSESSIONID'];
    if (sid && sessions.has(sid)) return sessions.get(sid);
    return null;
}

function createSession() {
    const sid = 'MOCK' + Date.now() + Math.random().toString(36).slice(2, 8);
    const session = { id: sid, loggedIn: false, step: 'search', pid: null, rrttrDate: null };
    sessions.set(sid, session);
    return session;
}

// ============================================================
// HTML Templates — mimic real NAP Plus structure
// ============================================================

function layoutWrap(title, body) {
    return `<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>${title}</title>
<style>
body { font-family: 'Tahoma', sans-serif; font-size: 13px; margin: 0; background: #f5f0e0; }
.header { background: linear-gradient(135deg, #c67a3a, #e8a85c); color: white; padding: 8px 15px; }
.header h1 { margin: 0; font-size: 16px; }
.content { padding: 20px; }
table.generalTable { border-collapse: collapse; width: 100%; background: white; margin: 10px 0; }
table.generalTable td { padding: 6px 10px; border: 1px solid #ddd; }
table.generalTable td:first-child { background: #f9f3e3; font-weight: bold; width: 200px; }
.generalTableHeader { background: #c67a3a; color: white; padding: 8px 10px; font-weight: bold; font-size: 14px; }
.generalTableTopic { padding: 5px 10px; font-weight: bold; color: #333; }
table.alert td.text { color: red; font-weight: bold; padding: 10px; }
table.result { border-collapse: collapse; width: 100%; background: white; }
table.result th { background: #e8d8b8; padding: 6px; border: 1px solid #ccc; }
table.result td { padding: 6px; border: 1px solid #ddd; }
.button { background: #c67a3a; color: white; border: none; padding: 8px 20px; cursor: pointer; font-size: 13px; margin: 3px; }
.button:hover { background: #a85a2a; }
input[type=text], select { padding: 4px 6px; border: 1px solid #ccc; font-size: 13px; }
input[type=checkbox], input[type=radio] { margin: 3px; }
.section { margin: 15px 0; padding: 10px; background: white; border: 1px solid #ddd; }
.section-title { background: #e8d8b8; padding: 6px 10px; font-weight: bold; margin: -10px -10px 10px -10px; }
.font22 { font-size: 22px; text-align: center; padding: 20px; }
.font22 b { color: #006600; }
label { margin-right: 15px; }
.condom-field { display: none; }
.form-row { margin: 8px 0; }
.form-label { display: inline-block; width: 180px; font-weight: bold; }
</style>
</head><body>
<div class="header">
    <h1>NAP<sup>plus</sup> — Mock Server (Testing Only)</h1>
    <div style="font-size:11px; color:#ffe0b0;">⚠️ นี่คือระบบจำลองสำหรับทดสอบ ไม่ได้เชื่อมกับ NAP Plus จริง</div>
</div>
<div class="content">${body}</div>
</body></html>`;
}

function loginPage(error = '') {
    const errorHtml = error ? `<div style="color:red; margin:10px 0; font-weight:bold;">${error}</div>` : '';
    return layoutWrap('Mock NAP Plus — Login', `
<h2>เข้าสู่ระบบ</h2>
${errorHtml}
<form action="/NAPPLUS/login.do" method="post">
    <input type="hidden" name="actionName" value="login" />
    <table class="generalTable" style="width:400px;">
        <tr><td>ชื่อผู้ใช้ :</td><td><input type="text" id="user_name" name="user_name" style="width:200px;" /></td></tr>
        <tr><td>รหัสผ่าน :</td><td><input type="password" id="password" name="password" style="width:200px;" /></td></tr>
        <tr><td colspan="2" style="text-align:center;">
            <input type="submit" value="Login" class="button" />
            <input type="button" name="Reset" value="เคลียร์" class="button" style="background:#888;" />
        </td></tr>
    </table>
</form>`);
}

function dashboardPage(username) {
    return layoutWrap('Mock NAP Plus — Dashboard', `
<h2>ยินดีต้อนรับ</h2>
<p>ชื่อผู้ใช้: <strong>${username}</strong></p>
<p>หน่วยงาน: <strong>คลินิกทดสอบ AutoNAP</strong></p>
<div style="background:#fff3cd; border:1px solid #ffc107; padding:15px; border-radius:5px; margin:20px 0;">
    <strong>📢 ข่าวประชาสัมพันธ์</strong><br>
    นี่คือ Mock Server สำหรับทดสอบระบบ AutoNAP
</div>`);
}

function searchPage() {
    return layoutWrap('Mock NAP Plus — ค้นหา', `
<div class="generalTableHeader">การให้บริการ Reach&amp;Recruit</div>
<form action="/NAPPLUS/rrttr/createRRTTR.do" method="post">
    <input type="hidden" name="actionName" value="search" />
    <div class="section">
        <div class="section-title">ค้นหาข้อมูล</div>
        <div class="form-row">
            <input type="radio" name="gr_type" value="0" checked /> ค้นหาด้วยเลขบัตรประชาชน
        </div>
        <div class="form-row">
            <span class="form-label">วันที่ให้บริการ :</span>
            <input type="text" id="rrttrDate" name="rrttrDate" placeholder="DD/MM/YYYY (พ.ศ.)" style="width:150px;" />
        </div>
        <div class="form-row">
            <span class="form-label">เลขบัตรประชาชน :</span>
            <input type="text" id="pid" name="pid" maxlength="13" style="width:200px;" />
            <input type="button" id="choosePerson" name="choosePerson" value="ค้นหาเลขประจำตัวประชาชนจากชื่อ และนามสกุล" class="button" style="font-size:11px;" />
        </div>
        <hr />
        <div class="form-row">
            <input type="radio" name="gr_type" value="1" /> ค้นหาด้วย UIC
        </div>
        <div class="form-row">
            <span class="form-label">วันที่ (UIC) :</span>
            <input type="text" id="rrttrDateAnonym" name="rrttrDateAnonym" style="width:150px;" />
        </div>
        <div class="form-row">
            <span class="form-label">UIC :</span>
            <input type="text" id="uic" name="uic" style="width:200px;" />
            <input type="button" id="chooseUic" name="chooseUic" value="ค้นหา UIC" class="button" style="font-size:11px;" />
        </div>
    </div>
    <div style="text-align:center; margin:15px 0;">
        <input type="submit" id="cmdSearch" value="เพิ่มข้อมูลให้บริการ" class="button" style="font-size:14px; padding:10px 30px;" />
        <input type="button" id="cmdBackToSearch" name="cmdBackToSearch" value="กลับไปหน้าค้นหา" class="button" style="background:#888;" />
    </div>
</form>`);
}

function confirmPage(pid, rrttrDate) {
    return layoutWrap('Mock NAP Plus — ยืนยันข้อมูล', `
<div class="generalTableHeader">แสดงข้อมูลสิทธิการรักษา</div>
<form action="/NAPPLUS/rrttr/createRRTTR.do" method="post">
    <input type="hidden" name="actionName" value="input" />
    <input type="hidden" name="gotoLog" value="N" />
    <input type="hidden" name="pid" value="${pid}" />
    <input type="hidden" name="rrttrDate" value="${rrttrDate}" />
    <input type="hidden" name="confirm_right" value="null" />

    <div class="generalTableTopic">ข้อมูลบุคคล</div>
    <table class="generalTable">
        <tr><td>วันที่ตรวจสอบสิทธิ</td><td>25/03/2569</td></tr>
        <tr><td>เลขประจำตัวประชาชน</td><td>${pid}</td></tr>
        <tr><td>ชื่อ-นามสกุล</td><td>นาย ทดสอบ ระบบอัตโนมัติ</td></tr>
        <tr><td>เดือน/ปี เกิด</td><td>ม.ค. 2542</td></tr>
        <tr><td>เพศ</td><td>ชาย</td></tr>
        <tr><td>ที่อยู่ตามทะเบียนบ้าน</td><td>123 ถ.ทดสอบ ต.ทดสอบ อ.เมือง จ.เชียงใหม่ 50200</td></tr>
    </table>

    <div class="generalTableTopic">ข้อมูลสิทธิ</div>
    <table class="generalTable">
        <tr><td>สิทธิหลักในการรับบริการ</td><td>สิทธิหลักประกันสุขภาพถ้วนหน้า</td></tr>
        <tr><td>จังหวัดที่ลงทะเบียนรักษา</td><td>เชียงใหม่</td></tr>
        <tr><td>สถานพยาบาลหลัก</td><td>โรงพยาบาลทดสอบ</td></tr>
    </table>

    <div class="generalTableHeader">ประวัติการให้บริการ Reach&amp;Recruit</div>
    <table class="result">
        <tr><th>ลำดับ</th><th>วันที่ให้บริการ</th><th>หน่วยบริการ</th><th>สิทธิ</th><th>สถานพยาบาลหลัก</th></tr>
        <tr><td colspan="5" style="text-align:center; color:#999;">ไม่พบประวัติ</td></tr>
    </table>

    <div style="text-align:center; margin:20px 0;">
        <input type="button" name="backBtn" value="ย้อนกลับ" class="button" style="background:#888;" />
        <input type="submit" name="registerBtn" value="ตกลง" class="button" style="font-size:14px; padding:10px 30px;" />
    </div>
</form>`);
}

function rrFormPage(pid, rrttrDate) {
    const riskBehaviors = [
        { idx: 0, val: 1, name: 'TG' }, { idx: 1, val: 2, name: 'MSM' },
        { idx: 2, val: 3, name: 'SW' }, { idx: 3, val: 4, name: 'PWID' },
        { idx: 4, val: 5, name: 'Migrant' }, { idx: 5, val: 6, name: 'Prisoner' },
    ];

    const targetGroups = [
        { idx: 0, val: 3, name: 'MSM', m: 'ROW_1_COL_1' },
        { idx: 1, val: 1, name: 'PWID', m: 'ROW_1_COL_2' },
        { idx: 2, val: 16, name: 'ANC', m: 'ROW_1_COL_3' },
        { idx: 3, val: 4, name: 'TGW', m: 'ROW_2_COL_1' },
        { idx: 4, val: 2, name: 'PWUD', m: 'ROW_2_COL_2' },
        { idx: 5, val: 17, name: 'คลอดจากแม่ติดเชื้อเอชไอวี', m: 'ROW_2_COL_3' },
        { idx: 6, val: 5, name: 'TGM', m: 'ROW_3_COL_1' },
        { idx: 7, val: 11, name: 'Partner of KP', m: 'ROW_3_COL_2' },
        { idx: 8, val: 14, name: 'บุคลากรทางการแพทย์ (Health Personnel)', m: 'ROW_3_COL_3' },
        { idx: 9, val: 10, name: 'TGSW', m: 'ROW_4_COL_1' },
        { idx: 10, val: 12, name: 'Partner of PLHIV', m: 'ROW_4_COL_2' },
        { idx: 11, val: 15, name: 'nPEP', m: 'ROW_4_COL_3' },
        { idx: 12, val: 8, name: 'MSW', m: 'ROW_5_COL_1' },
        { idx: 13, val: 7, name: 'Prisoners', m: 'ROW_5_COL_2' },
        { idx: 14, val: 13, name: 'General Population', m: 'ROW_5_COL_3' },
        { idx: 15, val: 9, name: 'FSW', m: 'ROW_6_COL_1' },
        { idx: 16, val: 6, name: 'Migrant', m: 'ROW_6_COL_2' },
        { idx: 17, val: 18, name: 'สามี/คู่ของหญิงตั้งครรภ์', m: 'ROW_6_COL_3' },
    ];

    const knowledge = [
        { idx: 0, val: 1, name: 'ให้ความรู้เรื่อง เอชไอวี' },
        { idx: 1, val: 2, name: 'ให้ความรู้เรื่อง โรคติดต่อทางเพศสัมพันธ์' },
        { idx: 2, val: 3, name: 'ให้ความรู้เรื่อง วัณโรค' },
        { idx: 3, val: 4, name: 'การลดอันตรายจากการใช้ยา' },
        { idx: 4, val: 5, name: 'ให้ความรู้เรื่อง ไวรัสตับอักเสบซี' },
    ];

    const places = [
        { idx: 0, val: 1, name: 'ให้ข้อมูลสถานที่ เอชไอวี' },
        { idx: 1, val: 2, name: 'ให้ข้อมูลสถานที่ โรคติดต่อทางเพศสัมพันธ์' },
        { idx: 2, val: 3, name: 'ให้ข้อมูลสถานที่ วัณโรค' },
        { idx: 3, val: 4, name: 'ให้ข้อมูลสถานที่ การรับยา เมทาโดน' },
        { idx: 4, val: 5, name: 'ให้ข้อมูลสถานที่ ไวรัสตับอักเสบซี (HCV)' },
    ];

    const ppes = [
        { idx: 0, val: 1, name: 'ถุงยางอนามัย' },
        { idx: 1, val: 2, name: 'ถุงยางอนามัยผู้หญิง' },
        { idx: 2, val: 3, name: 'สารหล่อลื่น' },
        { idx: 3, val: 4, name: 'อุปกรณ์ฉีดยาปลอดเชื้อ' },
        { idx: 4, val: 5, name: 'หน้ากากอนามัย' },
    ];

    const riskHtml = riskBehaviors.map(r => `
        <input type="hidden" name="rrttr_risk_behavior_${r.idx}" value="${r.val}" />
        <input type="hidden" name="rrttr_risk_behavior_name_${r.idx}" value="${r.name}" />
        <label><input type="checkbox" id="rrttr_risk_behavior_status_${r.idx}" name="rrttr_risk_behavior_status_${r.idx}" value="Y" /> ${r.name}</label>
    `).join('');

    const targetHtml = targetGroups.map(t => `
        <input type="hidden" name="rrttr_master_value_${t.idx}" value="${t.m}" />
        <input type="hidden" name="rrttr_target_group_${t.idx}" value="${t.val}" />
        <input type="hidden" name="rrttr_target_group_name_${t.idx}" value="${t.name}" />
        <label><input type="checkbox" id="rrttr_target_group_status_${t.idx}" name="rrttr_target_group_status_${t.idx}" value="Y" /> ${t.name}</label>
    `).join('');

    const knowledgeHtml = knowledge.map(k => `
        <input type="hidden" name="rrttr_knowledge_${k.idx}" value="${k.val}" />
        <input type="hidden" name="rrttr_knowledge_name_${k.idx}" value="${k.name}" />
        <label><input type="checkbox" id="rrttr_knowledge_status_${k.idx}" name="rrttr_knowledge_status_${k.idx}" value="Y" /> ${k.name}</label>
    `).join('');

    const placeHtml = places.map(p => `
        <input type="hidden" name="rrttr_place_${p.idx}" value="${p.val}" />
        <input type="hidden" name="rrttr_place_name_${p.idx}" value="${p.name}" />
        <label><input type="checkbox" id="rrttr_place_status_${p.idx}" name="rrttr_place_status_${p.idx}" value="Y" /> ${p.name}</label>
    `).join('');

    const ppeHtml = ppes.map(p => `
        <input type="hidden" name="rrttr_ppe_${p.idx}" value="${p.val}" />
        <input type="hidden" name="rrttr_ppe_name_${p.idx}" value="${p.name}" />
        <label><input type="checkbox" id="rrttr_ppe_status_${p.idx}" name="rrttr_ppe_status_${p.idx}" value="Y" /> ${p.name}</label>
    `).join('');

    const occupations = `
        <option value="">-- เลือก --</option>
        <option value="01">ไม่มี/ว่างงาน</option><option value="02">เกษตรกร</option>
        <option value="03">รับจ้างทั่วไป</option><option value="04">ช่างฝีมือ</option>
        <option value="05">เจ้าของกิจการ / ธุรกิจ</option><option value="06">ข้าราชการทหาร</option>
        <option value="15">พนักงาน/ลูกจ้างบริษัท</option><option value="16">ค้าขาย</option>
        <option value="20">นักเรียน/นักศึกษา</option><option value="22">ขายบริการทางเพศ</option>
        <option value="29">ไม่ระบุอาชีพ</option>`;

    return layoutWrap('Mock NAP Plus — แบบฟอร์ม Reach RR', `
<div class="generalTableHeader">การให้บริการผู้ติดเชื้อ/ผู้ป่วยเอดส์ » แบบฟอร์มบันทึก Reach&amp;Recruit</div>
<form id="rrForm" action="/NAPPLUS/rrttr/createRRTTR.do" method="post">
    <input type="hidden" name="actionName" value="preview" />
    <input type="hidden" id="target_group" name="target_group" value="" />
    <input type="hidden" name="ref_tumb_name" value="กลางเวียง" />
    <input type="hidden" name="ref_amph_name" value="เวียงสา" />
    <input type="hidden" name="ref_prov_name" value="น่าน" />
    <input type="hidden" name="volunteer_name" value="ทดสอบ ระบบอัตโนมัติ" />

    <div class="section">
        <div class="section-title">1. พฤติกรรมเสี่ยง</div>
        ${riskHtml}
    </div>

    <div class="section">
        <div class="section-title">2. กลุ่มเป้าหมาย (18 กลุ่ม)</div>
        ${targetHtml}
    </div>

    <div class="section">
        <div class="section-title">3. คู่นอน / ช่องทางเข้าถึง</div>
        <div class="form-row">
            <span class="form-label">คู่นอน/คู่เสพ :</span>
            <select id="partner_with" name="partner_with">
                <option value="">-- ไม่ระบุ --</option>
                <option value="1">PWID</option><option value="2">MSM</option>
                <option value="3">TG</option><option value="4">Migrant</option>
            </select>
            <input type="hidden" name="partner_with_name" value="" />
        </div>
        <div class="form-row">
            <span class="form-label">ช่องทางการเข้าถึง :</span>
            <label><input type="radio" id="access_type_1" name="access_type" value="1" /> ใน DIC</label>
            <label><input type="radio" id="access_type_2" name="access_type" value="2" /> นอก DIC</label>
            <label><input type="radio" id="access_type_3" name="access_type" value="3" /> สื่อสังคมออนไลน์</label>
        </div>
        <div class="form-row">
            <span class="form-label">สื่อสังคมออนไลน์ :</span>
            <select id="social_media" name="social_media">
                <option value="">-- ไม่ระบุ --</option>
                <option value="1">Facebook</option><option value="4">Line</option>
                <option value="7">Grindr</option>
            </select>
            <input type="hidden" name="social_media_name" value="" />
        </div>
        <div class="form-row">
            <span class="form-label">แหล่งเงิน :</span>
            <select id="pay_by" name="pay_by">
                <option value="">-- เลือก --</option>
                <option value="1">NHSO</option><option value="2">Global Fund</option>
                <option value="3">PEPFAR</option>
            </select>
            <input type="hidden" name="pay_by_name" value="" />
        </div>
    </div>

    <div class="section">
        <div class="section-title">4. ข้อมูลติดต่อ</div>
        <div class="form-row"><span class="form-label">ที่อยู่ :</span><input type="text" id="ref_addr" name="ref_addr" style="width:300px;" /></div>
        <div class="form-row">
            <span class="form-label">จังหวัด :</span>
            <select id="ref_province" name="ref_province"><option value="55000000">น่าน</option><option value="50000000">เชียงใหม่</option></select>
            <select id="ref_amphur" name="ref_amphur"><option value="55070000">เวียงสา</option></select>
            <select id="ref_tumbon" name="ref_tumbon"><option value="55070100">กลางเวียง</option></select>
        </div>
        <div class="form-row"><span class="form-label">รหัสไปรษณีย์ :</span><input type="text" id="ref_postal" name="ref_postal" /></div>
        <div class="form-row"><span class="form-label">โทรศัพท์ :</span><input type="text" id="ref_tel" name="ref_tel" /></div>
        <div class="form-row"><span class="form-label">Email :</span><input type="text" id="ref_email" name="ref_email" /></div>
    </div>

    <div class="section">
        <div class="section-title">5. อาชีพ</div>
        <div class="form-row">
            <span class="form-label">อาชีพ :</span>
            <select id="occupation" name="occupation">${occupations}</select>
            <input type="hidden" name="occupation_name" value="" />
        </div>
        <div class="form-row">
            <span class="form-label">ประเภท SW :</span>
            <label><input type="radio" id="sw_type_1" name="sw_type" value="1" /> ในสถานบริการ</label>
            <label><input type="radio" id="sw_type_2" name="sw_type" value="2" /> นอกสถานบริการ</label>
        </div>
    </div>

    <div class="section">
        <div class="section-title">6. การให้ความรู้</div>
        ${knowledgeHtml}
    </div>

    <div class="section">
        <div class="section-title">7. ข้อมูลสถานที่</div>
        ${placeHtml}
    </div>

    <div class="section">
        <div class="section-title">8. อุปกรณ์ป้องกัน (PPE)</div>
        ${ppeHtml}
        <div class="form-row" style="margin-top:10px;">
            <strong>ถุงยางอนามัย (ชิ้น):</strong><br />
            <label id="lb_condom_amount_49_1" style="display:none">49mm</label> <input id="rrttr_condom_amount_49" name="rrttr_condom_amount_49" type="text" style="width:50px; display:none;" class="condom-field" />
            <label id="lb_condom_amount_49_2" style="display:none"></label>
            <label id="lb_condom_amount_52_1" style="display:none">52mm</label> <input id="rrttr_condom_amount_52" name="rrttr_condom_amount_52" type="text" style="width:50px; display:none;" class="condom-field" />
            <label id="lb_condom_amount_52_2" style="display:none"></label>
            <label id="lb_condom_amount_53_1" style="display:none">53mm</label> <input id="rrttr_condom_amount_53" name="rrttr_condom_amount_53" type="text" style="width:50px; display:none;" class="condom-field" />
            <label id="lb_condom_amount_53_2" style="display:none"></label>
            <label id="lb_condom_amount_54_1" style="display:none">54mm</label> <input id="rrttr_condom_amount_54" name="rrttr_condom_amount_54" type="text" style="width:50px; display:none;" class="condom-field" />
            <label id="lb_condom_amount_54_2" style="display:none"></label>
            <label id="lb_condom_amount_56_1" style="display:none">56mm</label> <input id="rrttr_condom_amount_56" name="rrttr_condom_amount_56" type="text" style="width:50px; display:none;" class="condom-field" />
            <label id="lb_condom_amount_56_2" style="display:none"></label>
        </div>
        <div class="form-row">
            <label id="lb_female_condom_amount" style="display:none">ถุงยางหญิง:</label>
            <input id="rrttr_female_condom_amount" name="rrttr_female_condom_amount" type="text" style="width:50px; display:none;" />
        </div>
        <div class="form-row">
            <label id="lb_lubricant_amount" style="display:none">สารหล่อลื่น (ซอง):</label>
            <input id="rrttr_lubricant_amount" name="rrttr_lubricant_amount" type="text" style="width:50px; display:none;" />
        </div>
    </div>

    <div class="section">
        <div class="section-title">9. บริการส่งต่อ</div>
        <div class="form-row">
            <span class="form-label">รหัสหน่วยบริการ :</span>
            <input type="text" id="next_hcode" name="next_hcode" style="width:100px;" />
            <input type="text" id="next_hname" name="next_hname" style="width:200px;" readonly />
            <input type="hidden" id="next_hid" name="next_hid" value="null" />
            <input type="text" id="next_place" name="next_place" placeholder="สถานที่ (กรณีไม่พบในระบบ)" style="width:200px;" />
        </div>
        <table style="width:100%; margin-top:10px;">
            <tr><th></th><th>เจ้าหน้าที่พาไป</th><th>ไปเอง</th><th>ไม่ได้ส่งต่อ</th></tr>
            ${['hiv', 'sti', 'tb', 'hcv', 'methadone'].map(svc => `
            <tr>
                <td><strong>${svc.toUpperCase()}</strong></td>
                <td><input type="radio" id="${svc}_forward_1" name="${svc}_forward" value="1" /></td>
                <td><input type="radio" id="${svc}_forward_2" name="${svc}_forward" value="2" /></td>
                <td><input type="radio" id="${svc}_forward_3" name="${svc}_forward" value="3" /></td>
            </tr>`).join('')}
        </table>
    </div>

    <div style="text-align:center; margin:20px 0;">
        <input type="submit" name="confirmBtn" value="บันทึก" class="button" style="font-size:16px; padding:12px 40px;" />
        <input type="button" name="clearBtn" value="เคลียร์" class="button" style="background:#888;" />
        <input type="button" name="backBtn" value="กลับไปหน้าค้นหา" class="button" style="background:#888;" />
    </div>
</form>`);
}

function previewPage(formData) {
    const checked = (prefix, data) => {
        const items = [];
        for (let i = 0; i < 20; i++) {
            if (data[`${prefix}_status_${i}`] === 'Y') {
                items.push(data[`${prefix.replace('_status', '')}_name_${i}`] || `index ${i}`);
            }
        }
        return items.join(', ') || '-';
    };

    return layoutWrap('Mock NAP Plus — ยืนยันข้อมูลการให้บริการ', `
<div class="generalTableHeader">ยืนยันข้อมูลการให้บริการ Reach&amp;Recruit</div>
<form action="/NAPPLUS/rrttr/createRRTTR.do" method="post">
    <input type="hidden" name="actionName" value="confirm" />

    <table class="generalTable">
        <tr><td>พฤติกรรมเสี่ยง</td><td>${checked('rrttr_risk_behavior', formData)}</td></tr>
        <tr><td>กลุ่มเป้าหมาย</td><td>${checked('rrttr_target_group', formData)}</td></tr>
        <tr><td>ช่องทางการเข้าถึง</td><td>${formData.access_type === '1' ? 'ใน DIC' : formData.access_type === '3' ? 'สื่อสังคมออนไลน์' : 'นอก DIC'}</td></tr>
        <tr><td>อาชีพ</td><td>${formData.occupation_name || formData.occupation || '-'}</td></tr>
        <tr><td>โทรศัพท์</td><td>${formData.ref_tel || '-'}</td></tr>
        <tr><td>แหล่งเงิน</td><td>${formData.pay_by_name || formData.pay_by || '-'}</td></tr>
        <tr><td>การให้ความรู้</td><td>${checked('rrttr_knowledge', formData)}</td></tr>
        <tr><td>อุปกรณ์ป้องกัน</td><td>${checked('rrttr_ppe', formData)}</td></tr>
        <tr><td>ถุงยาง 49mm</td><td>${formData.rrttr_condom_amount_49 || '0'}</td></tr>
        <tr><td>ถุงยาง 52mm</td><td>${formData.rrttr_condom_amount_52 || '0'}</td></tr>
        <tr><td>ถุงยาง 54mm</td><td>${formData.rrttr_condom_amount_54 || '0'}</td></tr>
        <tr><td>ถุงยาง 56mm</td><td>${formData.rrttr_condom_amount_56 || '0'}</td></tr>
        <tr><td>สารหล่อลื่น</td><td>${formData.rrttr_lubricant_amount || '0'}</td></tr>
        <tr><td>หน่วยบริการส่งต่อ</td><td>${formData.next_hcode || '-'}</td></tr>
        <tr><td>HIV Forward</td><td>${formData.hiv_forward || '-'}</td></tr>
        <tr><td>STI Forward</td><td>${formData.sti_forward || '-'}</td></tr>
        <tr><td>TB Forward</td><td>${formData.tb_forward || '-'}</td></tr>
    </table>

    <div style="text-align:center; margin:20px 0;">
        <input type="button" name="backBtn" value="ย้อนกลับ" class="button" style="background:#888;" />
        <input type="submit" value="ตกลง" class="button" style="font-size:16px; padding:12px 40px;" />
    </div>
</form>`);
}

function successPage(rrCode) {
    return layoutWrap('Mock NAP Plus — บันทึกสำเร็จ', `
<div class="generalTableHeader">ผลการบันทึก</div>
<div style="text-align:center; padding:40px;">
    <div style="font-size:18px; color:green; margin-bottom:20px;">✅ บันทึกข้อมูลเรียบร้อย</div>
    <div class="font22"><b>${rrCode}</b></div>
    <div style="margin-top:20px; color:#666;">รหัสบันทึก Reach&amp;Recruit</div>
</div>`);
}

function errorPage(message) {
    return layoutWrap('Mock NAP Plus — ข้อผิดพลาด', `
<table class="alert"><tr><td class="text">${message}</td></tr></table>`);
}

// ============================================================
// Request Handler
// ============================================================

function parseBody(req) {
    return new Promise((resolve) => {
        let body = '';
        req.on('data', chunk => body += chunk);
        req.on('end', () => resolve(querystring.parse(body)));
    });
}

const server = http.createServer(async (req, res) => {
    const parsedUrl = url.parse(req.url, true);
    const path = parsedUrl.pathname;
    res.setHeader('Content-Type', 'text/html; charset=utf-8');

    // GET login page
    if (path.includes('login.jsp') || path === '/') {
        res.end(loginPage());
        return;
    }

    // POST login
    if (path.includes('login.do') && req.method === 'POST') {
        const body = await parseBody(req);
        const session = createSession();
        res.setHeader('Set-Cookie', `JSESSIONID=${session.id}; Path=/`);

        // Accept any credentials (mock)
        if (body.user_name && body.password) {
            session.loggedIn = true;
            session.username = body.user_name;
            console.log(`✅ Login: ${body.user_name}`);
            res.end(dashboardPage(body.user_name));
        } else {
            res.end(loginPage('ชื่อผู้ใช้ หรือรหัสผ่านไม่ถูกต้อง'));
        }
        return;
    }

    // createRRTTR.do — main form flow
    if (path.includes('createRRTTR.do')) {
        const session = getSession(req);

        if (req.method === 'GET') {
            // GET with actionName=load → search page
            res.end(searchPage());
            return;
        }

        const body = await parseBody(req);
        const action = body.actionName;

        console.log(`📋 Action: ${action} | PID: ${body.pid || '-'}`);

        switch (action) {
            case 'search': {
                if (!body.pid || !body.rrttrDate) {
                    res.end(errorPage('กรุณากรอกเลขบัตรประชาชน และวันที่ให้บริการ'));
                    return;
                }
                if (session) {
                    session.pid = body.pid;
                    session.rrttrDate = body.rrttrDate;
                    session.step = 'confirm';
                }
                res.end(confirmPage(body.pid, body.rrttrDate));
                break;
            }

            case 'input': {
                if (session) session.step = 'form';
                res.end(rrFormPage(body.pid, body.rrttrDate));
                break;
            }

            case 'preview': {
                if (session) session.step = 'preview';
                res.end(previewPage(body));
                break;
            }

            case 'confirm': {
                // Generate mock RR code
                rrCounter++;
                const rrCode = `RR-2026-${1450000 + rrCounter}`;
                console.log(`🎉 Success: ${rrCode}`);
                if (session) session.step = 'search';
                res.end(successPage(rrCode));
                break;
            }

            default:
                res.end(searchPage());
        }
        return;
    }

    // Fallback
    res.end(layoutWrap('Mock NAP Plus', '<p>Page not found</p>'));
});

server.listen(PORT, () => {
    console.log('');
    console.log('==========================================');
    console.log(`  🏥 Mock NAP Plus Server`);
    console.log(`  http://localhost:${PORT}`);
    console.log('==========================================');
    console.log('');
    console.log('Endpoints:');
    console.log(`  Login:    http://localhost:${PORT}/NAPPLUS/login.jsp`);
    console.log(`  Form:     http://localhost:${PORT}/NAPPLUS/rrttr/createRRTTR.do?actionName=load`);
    console.log('');
    console.log('Credentials: any username/password accepted');
    console.log('RR codes: auto-generated (RR-2026-145xxxx)');
    console.log('');
    console.log('Waiting for requests...');
    console.log('');
});
