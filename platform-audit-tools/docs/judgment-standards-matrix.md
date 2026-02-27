# V2 自动判断标准矩阵（业务可读版）

适用版本：`HG Audit 1.9.2`  
作用：录入真实数据后，系统按本矩阵自动输出等级、标签、总评与结论摘要。

---

## 1. 速度评级（提款耗时）

阈值来源：`hg_get_risk_config()['speed']`  
默认值：`instant=1`、`fast=15`、`slow=120`（分钟）

1. `duration <= instant`  
输出：`秒级出款`，评分 `+2`
2. `duration <= fast`  
输出：`快速出款`，评分 `+1`
3. `duration <= slow`  
输出：`出款时效正常`，评分 `0`
4. `duration > slow`  
输出：`出款偏慢`，评分 `-2`
5. 无耗时数据  
输出：`耗时数据缺失`，评分 `-1`

---

## 2. 汇率评级（损耗/偏差）

阈值来源：`hg_get_risk_config()['loss']`  
默认值：`normal=0.015`、`warn=0.03`

判定优先级：
1. 优先使用 `deviation_pct`
2. 回退使用 `loss_pct`

规则：
1. 指标 `> 0`  
输出：`汇率倒挂收益`，评分 `+1`
2. 损耗绝对值 `<= normal`  
输出：`汇率损耗正常`，评分 `+1`
3. 损耗绝对值 `<= warn`  
输出：`汇率偏差明显`，评分 `-1`
4. 损耗绝对值 `> warn`  
输出：`汇率暗扣严重`，评分 `-3`
5. 无汇率数据  
输出：`汇率数据缺失`，评分 `-1`

---

## 3. KYC 评级

输入：`kyc_status` / `wager_kyc_kyc_status`

1. `none`  
输出：`低KYC阻力`，评分 `+1`
2. `sms/id_card`  
输出：`轻度KYC`，评分 `0`
3. 其他非空常规值  
输出：`中度KYC`，评分 `-1`
4. `video/face/stuck`  
输出：`高强度KYC`，评分 `-2`
5. 无信息  
输出：`KYC信息不足`，评分 `-1`

---

## 4. 到账状态评级（结算结果）

输入：`audit_status` + `withdrawal_amount_received`

1. 成功且实到金额有效  
输出：`实测成功出金`，评分 `+2`
2. 失败/拦截或成功但实到金额无效  
输出：`存在出金失败风险`，评分 `-3`
3. 其余状态  
输出：`样本待复核`，评分 `-1`

---

## 5. 综合风险总评

总分公式：  
`总分 = 速度评分 + 汇率评分 + KYC评分 + 到账状态评分`

分档：
1. `总分 <= -4`  
输出：`综合高风险`（红色）
2. `总分 >= 1`  
输出：`综合低风险`（绿色）
3. 其余  
输出：`综合中风险`（橙色）

---

## 6. 自动输出结果（系统写入）

写入 `hg_read_model_v2`：
1. `evaluation.speed`
2. `evaluation.fx`
3. `evaluation.kyc`
4. `evaluation.settlement`
5. `evaluation.overall`
6. `evaluation.summary`

额外快速调用字段（meta）：
1. `hg_eval_overall_code`
2. `hg_eval_overall_label`
3. `hg_eval_overall_color`
4. `hg_eval_total_score`
5. `hg_eval_overall_tags`
6. `hg_eval_summary`
