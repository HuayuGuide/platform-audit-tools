# Platform Audit Tools — by HuayuGuide

> Independent withdrawal verification utilities for Chinese-speaking users.  
> 华语平台实测审计工具集 — [华娱导航 HuayuGuide.com](https://www.huayuguide.com) 独立审计系统核心组件

[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![WordPress](https://img.shields.io/badge/WordPress-6.x-blue.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-8.1+-purple.svg)](https://php.net)

---

## What is This?

This repository contains the **open-source utility components** extracted from the [HuayuGuide Audit & Risk Control System](https://www.huayuguide.com/audits/) — a platform verification tool designed to help overseas Chinese users assess online platform reliability through real-money withdrawal testing.

Unlike promotional affiliate sites, HuayuGuide operates on a **first-hand evidence principle**:

- Every audit record is backed by actual withdrawal tests with real funds
- Processing times are measured to the minute with timestamps
- Cross-currency FX losses are calculated with precision against live market rates
- KYC friction is classified and tagged automatically

The full system powers [huayuguide.com/audits/](https://www.huayuguide.com/audits/) — this repo exposes the reusable calculation layer.

---

## Components

### `WithdrawalTimeCalculator`

A standalone PHP utility for calculating and classifying withdrawal processing speed.

```php
use HuayuGuide\AuditTools\WithdrawalTimeCalculator;

$calc = new WithdrawalTimeCalculator();

// Format raw minutes into human-readable text (Chinese)
echo $calc->formatDuration(7.5);    // → "7.5分钟"
echo $calc->formatDuration(90.0);   // → "1.5小时"
echo $calc->formatDuration(0.3);    // → "秒级"

// Classify speed against configurable thresholds
$result = $calc->evaluateSpeed(8.0, [
    'instant' => 5,   // ≤5 min = instant
    'fast'    => 30,  // ≤30 min = fast
    'slow'    => 240, // ≤240 min = normal; >240 = slow
]);

// $result = [
//   'code'  => 'fast',
//   'label' => '快速出款',
//   'score' => 1,
//   'tags'  => ['快速出款'],
// ]
```

**Defensive behaviors:**
- Returns `unknown` state for `null`, negative, or `INF` inputs
- All thresholds are runtime-configurable (no hard-coded magic numbers)
- Negative duration guard: `calculateDurationFromTimestamps()` returns `null` if end < start
- Output is consumed directly by Schema.org structured data generators

---

### `FxLossCalculator`

Calculates hidden foreign exchange losses in cross-currency withdrawals.

```php
use HuayuGuide\AuditTools\FxLossCalculator;

$calc = new FxLossCalculator();

// Same-currency: USDT → USDT
$result = $calc->analyze(1000, 'USDT', 995);
// → loss_pct: 0.5%, loss_amount: 5 USDT

// Cross-currency: CNY → MYR (requires live rate)
$result = $calc->analyzeCross(5000, 'CNY', 3050, 'MYR', $referenceRate);
// → deviation_pct: calculated vs market mid-rate
// → severe_loss: bool (true if deviation > threshold)
```

Supported currencies: `CNY`, `MYR`, `HKD`, `USDT`, `BTC`, `ETH`

---

### `AuditReadModel` Schema

The canonical JSON structure used by every audit record in the HuayuGuide system.  
Published here so developers can build compatible tooling or data importers.

See [`schema/audit-record-read-model.json`](schema/audit-record-read-model.json)

---

## Architecture

The full system follows a **CQRS-inspired, Clean Architecture** pattern adapted for WordPress:

```
ACF Raw Data
    → Normalizer (field cleaning)
    → Validator (completeness check)
    → Rule Engine (auto-judgment)
    → Tag Engine (auto-labeling)
    → Read Model (pre-computed, stored as post meta)
    → Shortcode / REST API (read-only front-end)
```

This design ensures:
- Front-end rendering **never triggers recalculation** (P95 < 80ms)
- Business rules are isolated from presentation
- Any field failure degrades gracefully — no Fatal errors

Full architecture specification: [`docs/architecture-blueprint.md`](docs/architecture-blueprint.md)

---

## Background: Why This Exists

Overseas Chinese users face a unique problem: platforms marketed in Chinese often operate opaquely — unstated withdrawal fees, slow processing disguised as "processing time," KYC blocks triggered only at withdrawal stage, and cross-currency losses buried in exchange rates.

[华娱导航 HuayuGuide.com](https://www.huayuguide.com) was built to solve this through **reproducible, evidence-based auditing**:

1. [安全核验 Safety Check](https://www.huayuguide.com/safety-check/) — verify the platform entry point before depositing
2. [平台档案 Platform Archive](https://www.huayuguide.com/platforms/) — license info, operator entity, and T&C records
3. [牌照核验 License Check](https://www.huayuguide.com/license-check/) — verify regulator-issued license numbers against official databases
4. [实测风控榜 Audit Log](https://www.huayuguide.com/audits/) — real-money withdrawal tests with timestamps and evidence screenshots
5. [排行榜 Top List](https://www.huayuguide.com/toplist/) — platform rankings derived from audit data, not advertising fees

The tools in this repo are the calculation engine behind the audit log (step 4).

---

## Installation

### As a Standalone Library

```bash
composer require huayuguide/platform-audit-tools
```

### Manual (WordPress plugin context)

```php
require_once 'path/to/platform-audit-tools/src/WithdrawalTimeCalculator.php';
require_once 'path/to/platform-audit-tools/src/FxLossCalculator.php';
```

---

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 8.1+ |
| WordPress | 6.0+ (optional, tools work standalone) |
| ACF Pro | 6.x (optional, for full plugin integration) |

---

## Related Resources

| Resource | Link |
|----------|------|
| 实测风控榜单 Audit Log | [huayuguide.com/audits/](https://www.huayuguide.com/audits/) |
| 平台档案 Platform Archive | [huayuguide.com/platforms/](https://www.huayuguide.com/platforms/) |
| 牌照核验 License Check | [huayuguide.com/license-check/](https://www.huayuguide.com/license-check/) |
| 安全核验 Safety Check | [huayuguide.com/safety-check/](https://www.huayuguide.com/safety-check/) |
| 让球盘结算计算器 Asian Handicap | [huayuguide.com/sports-plays/handicap-asian/](https://www.huayuguide.com/sports-plays/handicap-asian/) |
| 新手指南 Beginner Guide | [huayuguide.com/guide/](https://www.huayuguide.com/guide/) |
| 专家团队 Expert Team | [huayuguide.com/expert/](https://www.huayuguide.com/expert/) |
| 合作与披露 Disclosure | [huayuguide.com/disclosure/](https://www.huayuguide.com/disclosure/) |

---

## License

MIT License — see [LICENSE](LICENSE)

Components in `src/` are free to use, modify, and redistribute.  
Audit data, shortcode rendering system, and platform records remain proprietary to HuayuGuide.com.

---

## Contributing

This is primarily a showcase repository. Bug reports and suggestions welcome via Issues.  
For audit methodology discussions, open a Discussion thread.
