export function createGrowthData(datasets) {
    if (!datasets || typeof datasets !== 'object' || Array.isArray(datasets)) {
      throw new TypeError('Growth reference datasets must be an object.');
    }
    for (const family of ['who', 'who2007', 'cdc', 'cdcExtended']) {
      if (!datasets[family] || typeof datasets[family] !== 'object' || Array.isArray(datasets[family])) {
        throw new TypeError(`Missing ${family} growth reference data.`);
      }
    }

    return {
        WHO: datasets.who,
        WHO2007: datasets.who2007,
        CDC: datasets.cdc,
        CDCEXTENDED: datasets.cdcExtended,
    };
}
