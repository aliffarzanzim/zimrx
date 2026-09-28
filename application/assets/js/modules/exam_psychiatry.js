/**
 * ZimRx - Clinical Exam: Psychiatry & Mental State (MSE)
 */
(function() {
  'use strict';

  function initExamPsychiatry() {
    // 9. Psychiatry & Mental State Examination (MSE) Handlers
    // =======================================================
    const psychWrapper = document.getElementById('psychiatry-wrapper');
    if (psychWrapper) {
      const modeTabs = psychWrapper.querySelectorAll('.zrx-exam-mode-tab[data-psych-tab]');
      const panes = psychWrapper.querySelectorAll('.zrx-psych-pane');
      const textarea = document.getElementById('psychiatry-findings');

      // Tab switching
      modeTabs.forEach(tab => {
        tab.addEventListener('click', function() {
          const target = this.getAttribute('data-psych-tab');
          modeTabs.forEach(t => t.classList.remove('active'));
          this.classList.add('active');

          panes.forEach(pane => {
            if (pane.id === `zrx-psych-pane-${target}`) {
              pane.removeAttribute('hidden');
              pane.classList.add('active');
            } else {
              pane.setAttribute('hidden', '');
              pane.classList.remove('active');
            }
          });
        });
      });

      function getFieldValue(name) {
        return psychWrapper.querySelector(`[data-psych-field="${name}"]`)?.value || '';
      }

      function setFieldValue(name, val) {
        const el = psychWrapper.querySelector(`[data-psych-field="${name}"]`);
        if (el) el.value = val;
      }

      function syncPsychSummary() {
        if (!textarea) return;

        const grooming = getFieldValue('grooming');
        const eyeContact = getFieldValue('eye-contact');
        const rapport = getFieldValue('rapport');
        const psychomotor = getFieldValue('psychomotor');
        const speechRate = getFieldValue('speech-rate');
        const speechCoherence = getFieldValue('speech-coherence');
        const mood = getFieldValue('mood');
        const affect = getFieldValue('affect');
        const thoughtForm = getFieldValue('thought-form');
        const thoughtContent = getFieldValue('thought-content');
        const hallucinations = getFieldValue('hallucinations');
        const orientation = getFieldValue('orientation');
        const concentration = getFieldValue('concentration');
        const suicideRisk = getFieldValue('suicide-risk');
        const violenceRisk = getFieldValue('violence-risk');
        const insightGrade = getFieldValue('insight-grade');
        const judgment = getFieldValue('judgment');

        const lines = ['Mental State Examination (MSE):'];
        lines.push(`• Appearance & Behaviour: ${grooming}; Eye Contact: ${eyeContact}; Rapport: ${rapport}; Psychomotor: ${psychomotor}.`);
        lines.push(`• Speech & Affect: Speech ${speechRate}, ${speechCoherence}. Mood: ${mood}; Affect: ${affect}.`);
        lines.push(`• Thought & Perception: Thought process ${thoughtForm}; Content: ${thoughtContent}. Perception: ${hallucinations}.`);
        lines.push(`• Cognition: ${orientation}; Attention/Concentration: ${concentration}.`);
        lines.push(`• Risk Assessment: Suicide/Self-Harm: ${suicideRisk}; Violence: ${violenceRisk}.`);
        lines.push(`• Insight & Judgment: ${insightGrade}; Judgment: ${judgment}.`);

        textarea.value = lines.join('\n');
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
      }

      psychWrapper.querySelectorAll('select').forEach(sel => {
        sel.addEventListener('change', syncPsychSummary);
      });

      // Quick presets
      psychWrapper.querySelectorAll('[data-psych-preset]').forEach(btn => {
        btn.addEventListener('click', function() {
          const p = this.getAttribute('data-psych-preset');

          if (p === 'mdd') {
            setFieldValue('grooming', 'Disheveled, unkempt, self-neglect');
            setFieldValue('eye-contact', 'Poor / Downcast / Avoidant');
            setFieldValue('rapport', 'Cooperative, easily established');
            setFieldValue('psychomotor', 'Psychomotor Retardation (Slowed movements)');
            setFieldValue('speech-rate', 'Slowed, low volume, increased latency');
            setFieldValue('speech-coherence', 'Coherent & relevant');
            setFieldValue('mood', 'Depressed / Low / Sad / Hopeless');
            setFieldValue('affect', 'Blunted / Flat affect (Restricted range)');
            setFieldValue('thought-form', 'Coherent, logical, goal-directed');
            setFieldValue('thought-content', 'Depressive cognitions (Worthlessness, Guilt, Hopelessness)');
            setFieldValue('hallucinations', 'No hallucinations or illusions');
            setFieldValue('orientation', 'Fully oriented to time, place, and person');
            setFieldValue('concentration', 'Significantly impaired concentration');
            setFieldValue('suicide-risk', 'Passive death wish without active intent / plan');
            setFieldValue('violence-risk', 'No violence or homicidal ideation');
            setFieldValue('insight-grade', 'Grade 5: Intellectual insight (accepts illness, does not apply to behavior)');
            setFieldValue('judgment', 'Intact social and personal judgment');
          } else if (p === 'mania') {
            setFieldValue('grooming', 'Eccentric / Bizarre attire');
            setFieldValue('eye-contact', 'Intense / Staring / Intrusive');
            setFieldValue('rapport', 'Over-familiar / Disinhibited');
            setFieldValue('psychomotor', 'Psychomotor Agitation (Restless, pacing)');
            setFieldValue('speech-rate', 'Pressured, rapid, loud (Mania)');
            setFieldValue('speech-coherence', 'Coherent & relevant');
            setFieldValue('mood', 'Euphoric / Elated / Expansive');
            setFieldValue('affect', 'Labile affect (Rapid mood swings)');
            setFieldValue('thought-form', 'Flight of ideas with rhyming/punning');
            setFieldValue('thought-content', 'Delusions of Grandeur (Omnipotent powers)');
            setFieldValue('hallucinations', 'No hallucinations or illusions');
            setFieldValue('orientation', 'Fully oriented to time, place, and person');
            setFieldValue('concentration', 'Easily distractible');
            setFieldValue('suicide-risk', 'No suicidal ideation, intent, or plan (Low Risk)');
            setFieldValue('violence-risk', 'Expressed verbal threats / Agitation');
            setFieldValue('insight-grade', 'Grade 1: Complete denial of mental illness');
            setFieldValue('judgment', 'Impaired judgment due to psychosis / mania');
          } else if (p === 'psychosis') {
            setFieldValue('grooming', 'Disheveled, unkempt, self-neglect');
            setFieldValue('eye-contact', 'Poor / Downcast / Avoidant');
            setFieldValue('rapport', 'Guarded / Suspicious');
            setFieldValue('psychomotor', 'Normal psychomotor activity');
            setFieldValue('speech-rate', 'Normal rate, rhythm, and volume');
            setFieldValue('speech-coherence', 'Coherent & relevant');
            setFieldValue('mood', 'Irritable / Dysphoric');
            setFieldValue('affect', 'Incongruent to thought content');
            setFieldValue('thought-form', 'Loosening of associations / Derailment');
            setFieldValue('thought-content', 'Persecutory / Paranoid delusions');
            setFieldValue('hallucinations', 'Auditory hallucinations (3rd person running commentary)');
            setFieldValue('orientation', 'Fully oriented to time, place, and person');
            setFieldValue('concentration', 'Easily distractible');
            setFieldValue('suicide-risk', 'Active suicidal ideation without intent or lethal plan');
            setFieldValue('violence-risk', 'Expressed verbal threats / Agitation');
            setFieldValue('insight-grade', 'Grade 1: Complete denial of mental illness');
            setFieldValue('judgment', 'Impaired judgment due to psychosis / mania');
          } else if (p === 'gad') {
            setFieldValue('grooming', 'Well-groomed, dressed appropriately');
            setFieldValue('eye-contact', 'Appropriate / Normal');
            setFieldValue('rapport', 'Cooperative, easily established');
            setFieldValue('psychomotor', 'Psychomotor Agitation (Restless, pacing)');
            setFieldValue('speech-rate', 'Normal rate, rhythm, and volume');
            setFieldValue('speech-coherence', 'Coherent & relevant');
            setFieldValue('mood', 'Anxious / Apprehensive / Tense');
            setFieldValue('affect', 'Reactive & congruent to mood');
            setFieldValue('thought-form', 'Coherent, logical, goal-directed');
            setFieldValue('thought-content', 'Obsessions (Intrusive, ego-dystonic thoughts)');
            setFieldValue('hallucinations', 'No hallucinations or illusions');
            setFieldValue('orientation', 'Fully oriented to time, place, and person');
            setFieldValue('concentration', 'Easily distractible');
            setFieldValue('suicide-risk', 'No suicidal ideation, intent, or plan (Low Risk)');
            setFieldValue('violence-risk', 'No violence or homicidal ideation');
            setFieldValue('insight-grade', 'Grade 6: True emotional insight (full awareness & treatment adherence)');
            setFieldValue('judgment', 'Intact social and personal judgment');
          } else if (p === 'normal-mse') {
            psychWrapper.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
          }

          syncPsychSummary();
        });
      });

      const normalBtn = psychWrapper.querySelector('[data-action="normal-psych"]');
      if (normalBtn) {
        normalBtn.addEventListener('click', () => {
          psychWrapper.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
          if (textarea) {
            textarea.value = 'Mental State Examination (MSE):\n• General: Well-groomed, appropriate eye contact, cooperative rapport, normal psychomotor activity.\n• Speech & Affect: Normal rate/tone, coherent. Euthymic mood, reactive & congruent affect.\n• Thought & Perception: Logical and goal-directed thought process. No delusions, obsessions, or hallucinations elicited.\n• Cognition: Oriented to time, place, and person. Concentration intact.\n• Risk Assessment: No suicidal or homicidal ideation (Low risk).\n• Insight & Judgment: Grade 6 true emotional insight. Judgment intact.';
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
          }
        });
      }

      const clearBtn = psychWrapper.querySelector('[data-action="clear-psych"]');
      if (clearBtn) {
        clearBtn.addEventListener('click', () => {
          psychWrapper.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
          if (textarea) {
            textarea.value = '';
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
          }
        });
      }
    }

    // =======================================================
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initExamPsychiatry);
  } else {
    initExamPsychiatry();
  }
})();
