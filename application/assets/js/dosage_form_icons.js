(function () {
    const ICON_BASE = 'assets/images/dosage-form-images/';

    const ICON_RULES = [
        { icon: 'tablet.svg', terms: 'tablet|tabelt|power|fast dissolving tablet|ds tablet|rapid tablet|tablets|tablet (film coated)|fc tablet|film coated tablet|film-coated tablet|tablet (chewable)|chewable tablet|chewable tabelt|chewable|recast|gum|gummy tablet|montek|tablet (dispersible)|dispersible tablet|chewable dispersible tablet|md tablet|mouth dissolving tablet|orodispersible tablet|odt|odt tablet|od tablet|dt tablet|orally dispersible tablet|tablet (effervescent)|effervescent tablet|flash tablet|oroflash tablet|ft tablet|mups tablet|tablet (extended release)|er tablet|extended release tablet|extented release tablet|xr tablet|tablet er|tablet (sustained release)|sr tablet|sustained release tablet|retard tablet|tablet (controlled release)|cr tablet|continued release tablet|tablet cr|tablet (enteric coated)|ec tablet|tablet ec|tablet (delayed release)|dr tablet|tablet dr|tablet (modified release)|mr tablet|tr tablet|sublingual tablet|oral lyophilisate tablets' },
        { icon: 'suppository.svg', terms: 'vaginal tablet|suppository|supplository|vaginal suppository|soft gelatin vag capsule|vaginal pessary' },
        { icon: 'sachet.svg', terms: 'oral soluble film|oral powder|powder|sachet|granules|granules for suspension|dry powder sachet|granules for solution|granules for oral solution|powder for oral suspension|effervescent powder|microgranules|oral granules|effervescent granules|oral saline' },
        { icon: 'kit.svg', terms: 'kit|strip' },
        { icon: 'capsule.svg', terms: 'capsule|capsul|capsule in vegetable shell|licap|soft capsule|soft gel capsule|soft gelatin capsule|sg capsule|sachet powder|eye capsule|capsule (extended release)|er capsule|capsule (sustained release)|sr capsule|capsule (controlled release)|cr capsule|controlled release capsule|capsule (enteric coated)|ec capsule|capsule ec|capsule (delayed release)|dr capsule|capsule (modified release)|m r capsule|mr capsule|capsule (timed release)|tr capsule|timed release capsule' },
        { icon: 'dpi_capsule.svg', terms: 'dpi capsule|dry powder inhalation capsule|dry powder inhalation capsule (dpi)|dry powder inhaler|inhalation capsule|dpi inhalation capsule|capsule (dpi)|dpi|convicap|cozycap|powder inhaler' },
        { icon: 'syrup.svg', terms: 'syrup|kids syrup|linctus|elixir|oral solution|concentrated oral solution|oral liquid|oral suspension|suspension|powder for suspension|dry powder for suspension|dry syrup|dry powder for syrup|pellets for suspension|suspention|oral drops|pfs|granules for suspension|gfs|paediatric suspension|taste masked powder for suspension|powder for solution|emulsion|oral emulsion' },
        { icon: 'dropper.svg', terms: 'paediatric drops|paediatric drop|pediatric drop|pediatric drops|pediatrics drops|oral drop' },
        { icon: 'drops.svg', terms: 'eye drops|eye drop|sterile eye drops|eye solution|sterile eye gel|ed|eye prep|ophthalmic solutions|ophthalmic solution|viscous eye drops|eye suspension|eye sus|opthalmic suspension|ophthalmic suspension|ophthalmic emulsion|ear drops|ear drop|otic solution|ear suspension|nasal drops|nasal drop|paediatric nasal drops|eye/ear drops|e/e drop|e/e drops|eye/nasal drop|eye & nasal drop|drops|drop|eye/ear/nasal drops|e/e nasal drops|eye & nasal drops' },
        { icon: 'nasal-spray.svg', terms: 'nasal spray' },
        { icon: 'inhaler.svg', terms: 'metered-dose inhaler (mdi)|aerosol inhalation|dose inhaler|hfa inhaler|inhalation aerosol|mdi|metered dose inhaler|metered-dose inhaler|hfa inhaler (cfc-free)|hfa refill (cfc-free)|inhaler|maxhaler|inhalation solution|inhalation|inhaler (mdi)|mdi inhaler' },
        { icon: 'mdpi.svg', terms: 'multidose dry powder inhaler (mdpi)|mdpi|inhaler powder' },
        { icon: 'nebuliser.svg', terms: 'nebuliser solution|nebuliser solution/ampule|respiratory solution|nebulizer solution|resperitory solution|respirator solution|nebuliser suspension|nebulizer suspension|nebuliser' },
        { icon: 'inhalation.svg', terms: 'solution for inhalation|inhalation gas|suspension for inhalation|respirator suspension' },
        { icon: 'injection.svg', terms: 'intratracheal suspension|injection|ampoules|bolus|powder for injection|im/iv/ intrathecal injection|injectable solution (oral & im)|injectable solution|penset injection|iv injection|iv injeciton|intravenous injection|doriject powder for iv injection|iv injectio|iv injection or infusion|injection/infusion|iv injection for infusion|sc injection|s/c injection|subcutaneous injection|pre filled injection|pre-filled|pre-filled injection|pre-filled syringe injection|prefilled injection|iv/iminjection|prefilled syringe injection|insulin|pre-fillled syringe|im/iv injection|iv/im injectio|iv/im injection|ketoxguard|im/sc injection|vials|iv/sc injection|v/sc injection|prefilled syringe|im/ia injection|sc/im/iv injection|intra-articular injection|intravitreal injection|intracameral injection|intraocular injection|intraspinal injection' },
        { icon: 'im-injection.svg', terms: 'im injection|syringe|viscoelastic solution' },
        { icon: 'iv-infusion.svg', terms: 'iv infusion|infusion|intravenous infusion|solution for infusion|lyophilized iv infusion|iv nfusion|liquid injection|lyophilized iv injection|saline|emulsion for infusion|ef infusion' },
        { icon: 'cream.svg', terms: 'cream|t/cream|junior cream|rectal cream|vaginal cream|oral gel|oral paste' },
        { icon: 'ointment.svg', terms: 'ointment|eye ointment|ophthalmic ointment|eye cream|eo|nasal ointment|rectal ointment|cream/ointment|eye/ear ointment|scalp ointment|gel|topical gel|emulgel|eye gel|ophthalmic gel|ophthalmic ge|vaginal gel|lotion|junior lotion' },
        { icon: 'shampoo.svg', terms: 'scalp solution|scalp lotion|shampoo' },
        { icon: 'spray.svg', terms: 'spray|topical spray|sublingual spray' },
        { icon: 'mouthwash.svg', terms: 'mouthwash|mouth wash|gargle & mouth wash|gargle mouthwash' },
        { icon: 'solution.svg', terms: 'dialysis solution|irrigation solution|rectal saline|solution' },
        { icon: 'soap.svg', terms: 'medicated bar|bar cleanser' },
        { icon: 'scrub.svg', terms: 'surgical scrub|scrub' },
        { icon: 'topical-powder.svg', terms: 'topical powder' },
        { icon: 'bandage.svg', terms: 'bandage|wound dressing bandage' },
        { icon: 'gas.svg', terms: 'gas' },
        { icon: 'nail-lacquer.svg', terms: 'nail lacquer' },
        { icon: 'serum.svg', terms: 'serum' },
        { icon: 'vaccine.svg', terms: 'vaccine' },
        { icon: 'oral-vaccine.svg', terms: 'oral vaccine' },
        { icon: 'hand-rub.svg', terms: 'hand rub|liquid gel|hand sanitizer|hand rub solution|antiseptic solution|hand rub (with dispenser)|hand sanitizer (pen)|hand sanitizer (with dispenser)' },
        { icon: 'topical-solution.svg', terms: 'topical solution|solutions|antiseptic topical solution|topical suspension' },
        { icon: 'liquid.svg', terms: 'liquid|sweet drops' },
        { icon: 'powder-milk.svg', terms: 'powder milk' },
        { icon: 'bp-machine.svg', terms: 'bp monitor device' },
        { icon: 'patch.svg', terms: 'transdermal patch|patch' },
        { icon: 'glucometer.svg', terms: 'glucometer' },
        { icon: 'insulin-device.svg', terms: 'insulin device|insulin pen device|pen needle|pen cartridge|penfill' },
        { icon: 'fallback.svg', terms: 'raw materials' }
    ];

    const normalize = (value) => String(value || '')
        .toLowerCase()
        .replace(/\u00a0/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

    const ICON_TERMS = ICON_RULES
        .flatMap((rule) => rule.terms.split('|').map((term) => ({ term: normalize(term), icon: rule.icon })))
        .sort((a, b) => b.term.length - a.term.length);

    const prefixIcon = (name) => {
        if (name.startsWith('TAB.')) return 'tablet.svg';
        if (name.startsWith('CAP.')) return 'capsule.svg';
        if (name.startsWith('SYP.')) return 'syrup.svg';
        if (name.startsWith('SUSP.')) return 'syrup.svg';
        if (name.startsWith('INJ.')) return 'injection.svg';
        if (name.startsWith('SUPP.')) return 'suppository.svg';
        if (name.startsWith('OINT.')) return 'ointment.svg';
        if (name.startsWith('CRM.')) return 'cream.svg';
        return '';
    };

    window.getDosageFormIcon = function getDosageFormIcon(form = '', name = '') {
        const normalizedForm = normalize(form);
        const normalizedName = normalize(name);
        const upperName = String(name || '').trim().toUpperCase();

        const exactMatch = ICON_TERMS.find((item) => item.term && item.term === normalizedForm);
        if (exactMatch) return ICON_BASE + exactMatch.icon;

        const prefixMatch = prefixIcon(upperName);
        if (prefixMatch) return ICON_BASE + prefixMatch;

        const fuzzyMatch = ICON_TERMS.find((item) => {
            if (!item.term || item.term.length < 3) return false;
            return normalizedForm.includes(item.term) || normalizedName.includes(item.term);
        });

        return ICON_BASE + (fuzzyMatch ? fuzzyMatch.icon : 'fallback.svg');
    };
})();
