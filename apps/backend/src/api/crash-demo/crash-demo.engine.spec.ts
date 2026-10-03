import { CrashDemoEngine } from '@gitroom/backend/api/crash-demo/crash-demo.engine';

describe('CrashDemoEngine', () => {
  it('generates deterministic multipliers with the same seed', () => {
    const config = {
      seed: 'seed-a',
      volatility: 1,
      roundFrequencyMs: 1000,
      durationRounds: 10,
      initialBankroll: 1000,
      minBet: 1,
      maxBet: 25,
      maxMultiplier: 100,
    };

    const engineA = new CrashDemoEngine(config.seed);
    const engineB = new CrashDemoEngine(config.seed);

    const seriesA = Array.from({ length: 5 }, () =>
      engineA.generateCrashMultiplier(config)
    );
    const seriesB = Array.from({ length: 5 }, () =>
      engineB.generateCrashMultiplier(config)
    );

    expect(seriesA).toEqual(seriesB);
  });

  it('builds a bounded decision from strategy settings', () => {
    const engine = new CrashDemoEngine('seed-b');
    const strategy = CrashDemoEngine.getDefaultStrategy('balanced');
    const decision = engine.buildDecision(
      1000,
      {
        seed: 'seed-b',
        volatility: 1,
        roundFrequencyMs: 1000,
        durationRounds: 100,
        initialBankroll: 1000,
        minBet: 2,
        maxBet: 10,
        maxMultiplier: 100,
      },
      strategy
    );

    expect(decision.betAmount).toBeGreaterThanOrEqual(2);
    expect(decision.betAmount).toBeLessThanOrEqual(10);
    expect(decision.targetCashout).toBe(strategy.targetCashout);
  });
});

