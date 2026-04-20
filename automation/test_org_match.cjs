/**
 * Tests for org_match.cjs — isSameOrg / stripOrgPrefix / parseDuplicateOrg
 *
 * Run: node automation/test_org_match.cjs
 */

const { parseDuplicateOrg, stripOrgPrefix, isSameOrg } = require('./org_match.cjs');

let pass = 0;
let fail = 0;

function eq(actual, expected, label) {
    if (actual === expected) {
        pass++;
        console.log(`  ✓ ${label}`);
    } else {
        fail++;
        console.error(`  ✗ ${label}`);
        console.error(`      expected: ${JSON.stringify(expected)}`);
        console.error(`      actual:   ${JSON.stringify(actual)}`);
    }
}

function same(ours, theirName, label) {
    const result = isSameOrg(ours, { code: 'X0000', name: theirName });
    eq(result, true, `SAME: ${label}`);
}

function different(ours, theirName, label) {
    const result = isSameOrg(ours, { code: 'X0000', name: theirName });
    eq(result, false, `DIFF: ${label}`);
}

// ============================================================
console.log('\n[parseDuplicateOrg]');
// ============================================================

{
    const r = parseDuplicateOrg('วันที่ให้บริการซ้ำกับ G1440 มูลนิธิน้ำกว๊านสีรุ้ง');
    eq(r?.code, 'G1440', 'extract code');
    eq(r?.name, 'มูลนิธิน้ำกว๊านสีรุ้ง', 'extract name');
}

eq(parseDuplicateOrg(''), null, 'empty returns null');
eq(parseDuplicateOrg('no match here'), null, 'no pattern returns null');

// ============================================================
console.log('\n[stripOrgPrefix]');
// ============================================================

eq(stripOrgPrefix(''), '', 'empty');
eq(stripOrgPrefix('มูลนิธิน้ำกว๊านสีรุ้ง'), 'น้ำกว๊านสีรุ้ง', 'strip มูลนิธิ');
eq(stripOrgPrefix('ศูนย์บริการสุขภาพที่เป็นมิตรน้ำกว๊านสีรุ้ง'), 'น้ำกว๊านสีรุ้ง', 'strip ศูนย์บริการสุขภาพที่เป็นมิตร');
eq(stripOrgPrefix('F0380 มูลนิธิเอ็มพลัส จังหวัดเชียงใหม่'), 'เอ็มพลัส', 'strip hcode + มูลนิธิ + จังหวัดX');
eq(stripOrgPrefix('เอ็มพลัสสหคลินิก'), 'เอ็มพลัส', 'strip สหคลินิก suffix');
eq(stripOrgPrefix('มูลนิธิเพื่อนพนักงานบริการ สำนักงานกรุงเทพ'), 'เพื่อนพนักงานบริการ', 'strip มูลนิธิ + สำนักงานX');

// ============================================================
console.log('\n[isSameOrg — same org, different legal forms]');
// ============================================================

// Regression case: mplus_cmi (user-reported bug 2026-04-20)
same(
    'เอ็มพลัสสหคลินิก',
    'มูลนิธิเอ็มพลัส จังหวัดเชียงใหม่',
    'mplus_cmi clinic arm vs foundation arm (user-reported)',
);

// Known working case
same(
    'ศูนย์บริการสุขภาพที่เป็นมิตรน้ำกว๊านสีรุ้ง',
    'มูลนิธิน้ำกว๊านสีรุ้ง',
    'namkwan operating name vs foundation',
);

// Same org, identical name
same(
    'มูลนิธิเอ็มพลัส',
    'มูลนิธิเอ็มพลัส',
    'identical name',
);

// Same with hcode in theirs
same(
    'เอ็มพลัสสหคลินิก',
    'F0380 มูลนิธิเอ็มพลัส จังหวัดเชียงใหม่',
    'hcode-prefixed duplicate message',
);

// ============================================================
console.log('\n[isSameOrg — different orgs, must NOT match]');
// ============================================================

different(
    'เอ็มพลัสสหคลินิก',
    'มูลนิธิซิสเตอร์',
    'mplus vs sisters',
);

different(
    'มูลนิธิน้ำกว๊านสีรุ้ง',
    'มูลนิธิเอ็มพลัส',
    'namkwan vs mplus',
);

different(
    'มูลนิธิรักษ์ไทย',
    'มูลนิธิเพื่อนพนักงานบริการ',
    'rakthai vs swing',
);

// ============================================================
console.log('\n[isSameOrg — edge cases]');
// ============================================================

eq(isSameOrg(null, { name: 'anything' }), false, 'null our name');
eq(isSameOrg('anything', null), false, 'null their org');
eq(isSameOrg('', { name: 'x' }), false, 'empty our name');

// ============================================================
console.log(`\n${pass} passed, ${fail} failed`);
// ============================================================

process.exit(fail > 0 ? 1 : 0);
