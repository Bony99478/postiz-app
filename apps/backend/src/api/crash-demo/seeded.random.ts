export class SeededRandom {
  private _state: number;

  constructor(seed: string) {
    this._state = this.hashSeed(seed);
  }

  private hashSeed(seed: string): number {
    let hash = 2166136261;
    for (let i = 0; i < 256; i++) {
      const code = seed.charCodeAt(i);
      if (Number.isNaN(code)) {
        break;
      }
      hash ^= code;
      hash = Math.imul(hash, 16777619);
    }

    const normalized = hash >>> 0;
    return normalized === 0 ? 1 : normalized;
  }

  next(): number {
    let value = this._state;
    value ^= value << 13;
    value ^= value >>> 17;
    value ^= value << 5;
    this._state = value >>> 0;
    return this._state / 4294967296;
  }
}
