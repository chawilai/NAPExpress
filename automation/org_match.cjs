/**
 * Org name matching helpers for RR duplicate handling.
 *
 * Thai orgs often have multiple legal identities for the same branch:
 *   - "เอ็มพลัสสหคลินิก" (operating clinic arm)
 *   - "มูลนิธิเอ็มพลัส จังหวัดเชียงใหม่" (foundation arm)
 *   - "ศูนย์บริการสุขภาพที่เป็นมิตรน้ำกว๊านสีรุ้ง" (operating name)
 *   - "มูลนิธิน้ำกว๊านสีรุ้ง" (foundation name)
 *
 * isSameOrg() decides whether a UIC claimed by org X under one legal name
 * is actually the same operational entity as our logged-in site — so we
 * can look up the existing RR code instead of failing.
 */

/**
 * Parse a NAP duplicate error to extract the competing org info.
 * Example: "วันที่ให้บริการซ้ำกับ G1440 มูลนิธิน้ำกว๊านสีรุ้ง"
 * Returns: { code: "G1440", name: "มูลนิธิน้ำกว๊านสีรุ้ง" } or null
 */
function parseDuplicateOrg(errorMessage) {
    const match = (errorMessage || '').match(/ซ้ำกับ\s+(\S+)\s+(.+?)$/);

    if (match) {
        return { code: match[1].trim(), name: match[2].trim() };
    }

    return null;
}

/**
 * Strip generic Thai legal-entity prefixes AND location/type suffixes
 * to get the core/unique org identifier.
 *
 *   "เอ็มพลัสสหคลินิก"                  → "เอ็มพลัส"
 *   "มูลนิธิเอ็มพลัส จังหวัดเชียงใหม่"      → "เอ็มพลัส"
 *   "ศูนย์บริการสุขภาพที่เป็นมิตรน้ำกว๊าน" → "น้ำกว๊าน"
 *   "มูลนิธิน้ำกว๊านสีรุ้ง"               → "น้ำกว๊านสีรุ้ง"
 */
function stripOrgPrefix(name) {
    if (!name) {
        return '';
    }

    let n = name.trim();

    // Remove hcode prefix like "G1440 " or "F0380 "
    n = n.replace(/^[A-Z]\d{3,5}\s+/, '');

    // Generic legal-entity PREFIXES (longest first to avoid partial matches).
    // IMPORTANT: Do NOT add org-specific prefixes like "มูลนิธิเอ็มพลัส" here —
    // that would strip the unique identifier we need for matching.
    const prefixes = [
        'ศูนย์บริการสุขภาพที่เป็นมิตร',
        'คลินิกเทคนิคการแพทย์',
        'ศูนย์บริการ',
        'ศูนย์',
        'มูลนิธิ',
        'สมาคม',
        'สหคลินิก',
        'คลินิก',
        'บริษัท',
        'ห้าง',
        'กลุ่มแอ็คทีม',
        'กลุ่มเพื่อน',
        'กลุ่ม',
        'ชมรม',
        'เครือข่าย',
    ];

    // Iteratively strip prefix + suffix until no further change
    // (handles combos like "มูลนิธิXYZ สำนักงานกรุงเทพ" → "XYZ")
    let prev;

    do {
        prev = n;

        const lower = n.toLowerCase();

        for (const p of prefixes) {
            if (lower.startsWith(p.toLowerCase())) {
                n = n.slice(p.length).trim();
                break;
            }
        }

        // Location/branch SUFFIXES: " จังหวัดX", " สาขาX", " สำนักงานX"
        n = n.replace(/\s+(จังหวัด|สาขา|สำนักงาน)\S+\s*$/u, '').trim();

        // Type indicators stuck at the end without a space
        n = n.replace(/(สหคลินิก|คลินิก)\s*$/u, '').trim();
    } while (prev !== n);

    return n.toLowerCase();
}

/**
 * Check if the duplicate org is the SAME operational entity as our logged-in org.
 *
 * Cost of false-positive: lookup attempt returns nothing → falls back to original
 * error. Cost of false-negative: user sees "claimed by another org" fail when we
 * could have reused the existing RR code. Prefer matching more over less.
 */
function isSameOrg(napSiteName, duplicateOrg) {
    if (!napSiteName || !duplicateOrg) {
        return false;
    }

    const ourCore = stripOrgPrefix(napSiteName);
    const theirCore = stripOrgPrefix(duplicateOrg.name);

    if (!ourCore || !theirCore) {
        return false;
    }

    // Exact core match
    if (ourCore === theirCore) {
        return true;
    }

    // Partial core match (one contains the other) — handles cases where
    // suffix stripping incompletely aligns the two forms.
    if (ourCore.includes(theirCore) || theirCore.includes(ourCore)) {
        return true;
    }

    // Fallback: full name substring (original pre-strip logic)
    const ourFull = napSiteName.trim().toLowerCase();
    const theirFull = duplicateOrg.name.trim().toLowerCase();

    return ourFull.includes(theirFull) || theirFull.includes(ourFull);
}

module.exports = { parseDuplicateOrg, stripOrgPrefix, isSameOrg };
