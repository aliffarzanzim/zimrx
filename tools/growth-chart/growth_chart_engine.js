function normalCdf(z) {
    const a1 = 0.254829592, a2 = -0.284496736, a3 = 1.421413741;
    const a4 = -1.453152027, a5 = 1.061405429, p = 0.3275911;
    const sign = z < 0 ? -1 : 1;
    const x = Math.abs(z) / Math.sqrt(2.0);
    const t = 1.0 / (1.0 + p * x);
    const y = 1.0 - (((((a5 * t + a4) * t + a3) * t + a2) * t + a1) * t) * Math.exp(-x * x);
    return 0.5 * (1.0 + sign * y);
  }

  function getLmsAtAge(dataset, ageMonths) {
    if (!dataset || dataset.length === 0) return null;
    const clampedAge = Math.max(dataset[0].m, Math.min(dataset[dataset.length - 1].m, ageMonths));
    for (let i = 0; i < dataset.length - 1; i++) {
      const curr = dataset[i];
      const next = dataset[i + 1];
      if (clampedAge >= curr.m && clampedAge <= next.m) {
        const span = next.m - curr.m;
        const frac = span > 0 ? (clampedAge - curr.m) / span : 0;
        return {
          m: clampedAge,
          L: curr.L + frac * (next.L - curr.L),
          M: curr.M + frac * (next.M - curr.M),
          S: curr.S + frac * (next.S - curr.S)
        };
      }
    }
    return dataset[dataset.length - 1];
  }

  function calculateZScore(value, ageMonths, gender = 'Male', metric = 'weight', standard = 'auto') {
    const numVal = parseFloat(value);
    const numAge = parseFloat(ageMonths);
    if (isNaN(numVal) || isNaN(numAge) || numVal <= 0 || numAge < 0) {
      return null;
    }

    const isBoy = String(gender).toLowerCase().startsWith('m') || String(gender).toLowerCase().startsWith('b');
    const gSuffix = isBoy ? '_boys' : '_girls';

    let useStandard = standard;
    if (useStandard === 'auto') {
      useStandard = numAge < 24 ? 'who' : (numAge <= 60 ? 'who' : 'cdc');
    }

    let datasetKey = '';
    let ds = null;

    if (metric === 'weight' || metric === 'wfa') {
      datasetKey = 'wfa' + gSuffix;
    } else if (metric === 'height' || metric === 'length' || metric === 'lhfa' || metric === 'hfa') {
      datasetKey = (useStandard === 'who' ? 'lhfa' : 'hfa') + gSuffix;
    } else if (metric === 'hc' || metric === 'head' || metric === 'hcfa') {
      datasetKey = 'hcfa' + gSuffix;
      useStandard = 'who';
    } else if (metric === 'bmi' || metric === 'bmifa') {
      datasetKey = 'bmifa' + gSuffix;
    }

    const dataStore = (useStandard === 'who' ? WHO_DATA : CDC_DATA);
    ds = dataStore[datasetKey] || WHO_DATA[datasetKey] || CDC_DATA[datasetKey];

    if (!ds) return null;

    const lms = getLmsAtAge(ds, numAge);
    if (!lms || lms.M <= 0) return null;

    const L = lms.L;
    const M = lms.M;
    const S = lms.S;

    let z = 0;
    if (Math.abs(L) < 0.0001) {
      z = Math.log(numVal / M) / S;
    } else {
      z = (Math.pow(numVal / M, L) - 1.0) / (L * S);
    }

    // A. WHO adjustment for extreme Z-scores (variance restriction outside [-3, +3])
    if (useStandard === 'who' && (metric === 'weight' || metric === 'wfa' || metric === 'bmi' || metric === 'bmifa')) {
      if (z > 3) {
        const sd3pos = (Math.abs(L) < 0.0001) ? M * Math.exp(S * 3) : M * Math.pow(1 + L * S * 3, 1 / L);
        const sd2pos = (Math.abs(L) < 0.0001) ? M * Math.exp(S * 2) : M * Math.pow(1 + L * S * 2, 1 / L);
        const diff = sd3pos - sd2pos;
        if (diff > 0) {
          z = 3 + ((numVal - sd3pos) / diff);
        }
      } else if (z < -3) {
        const sd3neg = (Math.abs(L) < 0.0001) ? M * Math.exp(S * -3) : M * Math.pow(1 + L * S * -3, 1 / L);
        const sd2neg = (Math.abs(L) < 0.0001) ? M * Math.exp(S * -2) : M * Math.pow(1 + L * S * -2, 1 / L);
        const diff = sd2neg - sd3neg;
        if (diff > 0) {
          z = -3 + ((numVal - sd3neg) / diff);
        }
      }
    }

    // B. CDC 2022 Extended BMI tracking for severe obesity (>= 95th percentile)
    let extendedBmi = null;
    if (useStandard === 'cdc' && (metric === 'bmi' || metric === 'bmifa')) {
      const z95 = 1.64485;
      const bmiP95 = (Math.abs(L) < 0.0001) ? M * Math.exp(S * z95) : M * Math.pow(1 + L * S * z95, 1 / L);
      if (bmiP95 > 0) {
        const pctBmiP95 = (numVal / bmiP95) * 100;
        let obesityClass = null;
        if (pctBmiP95 >= 140 || numVal >= 40) {
          obesityClass = 'Class 3 Severe Obesity (≥140% of 95th %ile or BMI ≥40)';
        } else if (pctBmiP95 >= 120 || numVal >= 35) {
          obesityClass = 'Class 2 Severe Obesity (≥120% of 95th %ile or BMI ≥35)';
        } else if (pctBmiP95 >= 100 || numVal >= 30) {
          obesityClass = 'Class 1 Obesity (≥95th %ile to <120%)';
        }
        extendedBmi = {
          bmiP95: Math.round(bmiP95 * 10) / 10,
          pctBmiP95: Math.round(pctBmiP95 * 10) / 10,
          obesityClass: obesityClass
        };
      }
    }

    const percentile = Math.min(99.9, Math.max(0.1, normalCdf(z) * 100));

    return {
      value: numVal,
      ageMonths: numAge,
      gender: isBoy ? 'Boy' : 'Girl',
      metric: metric,
      standard: useStandard.toUpperCase(),
      zScore: Math.round(z * 100) / 100,
      percentile: Math.round(percentile * 10) / 10,
      median: Math.round(M * 10) / 10,
      lms: lms,
      extendedBmi: extendedBmi
    };
  }

  function getGrowthClassification(zScoreResult) {
    if (!zScoreResult) return { label: '--', status: 'normal', color: '#64748b' };
    const z = zScoreResult.zScore;
    const m = zScoreResult.metric;

    // Weight-for-Age (WHO: WFA does not diagnose overweight/obesity)
    if (m === 'weight' || m === 'wfa') {
      if (z < -3) return { label: 'Severely Underweight (< -3 SD)', status: 'severe', color: '#dc2626' };
      if (z < -2) return { label: 'Underweight (-2 to -3 SD)', status: 'warning', color: '#ea580c' };
      if (z <= 1) return { label: 'Normal Weight (-2 to +1 SD)', status: 'normal', color: '#16a34a' };
      if (z <= 2) return { label: 'High Weight-for-Age (+1 to +2 SD)', status: 'info', color: '#0284c7' };
      return { label: 'Growth/Weight Excess (> +2 SD, Assess Height & BMI)', status: 'warning', color: '#d97706' };
    }

    // Height / Length for Age
    if (m === 'height' || m === 'length' || m === 'lhfa' || m === 'hfa') {
      if (z < -3) return { label: 'Severely Stunted (< -3 SD)', status: 'severe', color: '#dc2626' };
      if (z < -2) return { label: 'Stunted (-2 to -3 SD)', status: 'warning', color: '#ea580c' };
      if (z <= 2) return { label: 'Normal Stature (-2 to +2 SD)', status: 'normal', color: '#16a34a' };
      return { label: 'Tall Stature (> +2 SD)', status: 'info', color: '#0284c7' };
    }

    // Head Circumference for Age (OFC)
    if (m === 'hc' || m === 'head' || m === 'hcfa') {
      if (z < -2) return { label: 'Microcephaly (< -2 SD)', status: 'severe', color: '#dc2626' };
      if (z <= 2) return { label: 'Normal Head Circumference', status: 'normal', color: '#16a34a' };
      return { label: 'Macrocephaly (> +2 SD)', status: 'warning', color: '#dc2626' };
    }

    // BMI for Age (Diagnostic for Wasting, Overweight, and Obesity)
    if (m === 'bmi' || m === 'bmifa') {
      if (z < -3) return { label: 'Severe Wasting (< -3 SD)', status: 'severe', color: '#dc2626' };
      if (z < -2) return { label: 'Wasting (-2 to -3 SD)', status: 'warning', color: '#ea580c' };
      if (z <= 1) return { label: 'Normal BMI for Age', status: 'normal', color: '#16a34a' };
      if (z <= 2) return { label: 'Overweight (+1 to +2 SD)', status: 'warning', color: '#d97706' };
      
      // CDC Extended BMI classification if available
      if (zScoreResult.extendedBmi?.obesityClass) {
        return {
          label: `${zScoreResult.extendedBmi.obesityClass} (${zScoreResult.extendedBmi.pctBmiP95}% of 95th)`,
          status: 'severe',
          color: '#dc2626'
        };
      }
      return { label: 'Obese (> +2 SD / ≥95th %ile)', status: 'severe', color: '#dc2626' };
    }

    return { label: `Z: ${z.toFixed(2)} (${zScoreResult.percentile}th %ile)`, status: 'normal', color: '#16a34a' };
  }

  function getMUACClassification(muacCm, ageMonths = 12) {
    const val = parseFloat(muacCm);
    if (isNaN(val) || val <= 0) return null;
    const age = parseFloat(ageMonths) || 12;

    // Age validation note for standard WHO/UNICEF tape (6-59 months)
    const isOutOfStandardAge = age < 6 || age > 59;
    const ageNote = isOutOfStandardAge ? (age < 6 ? ' (Note: <6m tape limits)' : ' (Note: >59m tape limits)') : '';

    if (val < 11.5) {
      return {
        label: `SAM (Severe Acute Malnutrition)${ageNote}`,
        category: 'Red',
        color: '#dc2626',
        value: val,
        isOutOfStandardAge
      };
    } else if (val < 12.5) {
      return {
        label: `MAM (Moderate Acute Malnutrition)${ageNote}`,
        category: 'Yellow',
        color: '#d97706',
        value: val,
        isOutOfStandardAge
      };
    } else {
      return {
        label: `Normal Nutritional Status${ageNote}`,
        category: 'Green',
        color: '#16a34a',
        value: val,
        isOutOfStandardAge
      };
    }
  }

  function getAgeInMonths(ageValue, ageUnit, dobStr) {
    if (dobStr) {
      const parts = dobStr.split(/[/.-]/);
      if (parts.length === 3) {
        let d, m, y;
        if (parts[0].length === 4) { y = parseInt(parts[0], 10); m = parseInt(parts[1], 10) - 1; d = parseInt(parts[2], 10); }
        else { d = parseInt(parts[0], 10); m = parseInt(parts[1], 10) - 1; y = parseInt(parts[2], 10); }
        const dob = new Date(y, m, d);
        if (!isNaN(dob.getTime())) {
          const now = new Date();
          let months = (now.getFullYear() - dob.getFullYear()) * 12 + (now.getMonth() - dob.getMonth());
          const dDiff = now.getDate() - dob.getDate();
          if (dDiff < 0) months -= 1;
          months += Math.max(0, dDiff) / 30.4375;
          if (months >= 0) return Math.round(months * 10) / 10;
        }
      }
    }
    const val = parseFloat(ageValue);
    if (isNaN(val) || val < 0) return 0;
    const unit = String(ageUnit || 'Years').toLowerCase();
    if (unit.startsWith('d')) return Math.round((val / 30.4375) * 10) / 10;
    if (unit.startsWith('w')) return Math.round(((val * 7) / 30.4375) * 10) / 10;
    if (unit.startsWith('m')) return Math.round(val * 10) / 10;
    return Math.round(val * 12 * 10) / 10;
  }
