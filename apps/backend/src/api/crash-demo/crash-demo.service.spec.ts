import { CrashDemoService } from '@gitroom/backend/api/crash-demo/crash-demo.service';
import { CrashDemoEngine } from '@gitroom/backend/api/crash-demo/crash-demo.engine';

describe('CrashDemoService', () => {
  it('runs rounds and computes stats', () => {
    const service = new CrashDemoService();
    const strategy = CrashDemoEngine.getDefaultStrategy('balanced');
    const session = service.startSession({
      orgId: 'org-1',
      config: {
        seed: 'org-1-seed',
        volatility: 1,
        roundFrequencyMs: 1000,
        durationRounds: 20,
        initialBankroll: 1000,
        minBet: 1,
        maxBet: 50,
        maxMultiplier: 100,
      },
      strategy,
    });

    const updated = service.runRounds('org-1', session.id, 10);

    expect(updated.rounds.length).toBeGreaterThan(0);
    expect(updated.stats.roundsPlayed).toBe(updated.rounds.length);
    expect(updated.stats.wins + updated.stats.losses).toBe(updated.rounds.length);
    expect(updated.stats.cashoutDistribution['1.00-1.49']).toBeDefined();
  });

  it('stops when stop-loss is reached', () => {
    const service = new CrashDemoService();
    const session = service.startSession({
      orgId: 'org-2',
      config: {
        seed: 'very-lossy-seed',
        volatility: 0.2,
        roundFrequencyMs: 1000,
        durationRounds: 100,
        initialBankroll: 100,
        minBet: 1,
        maxBet: 30,
        maxMultiplier: 3,
      },
      strategy: {
        profile: 'aggressive',
        targetCashout: 3,
        betFraction: 0.5,
        stopLossPct: 0.2,
        takeProfitPct: 2,
      },
    });

    const updated = service.runRounds('org-2', session.id, 1000);
    expect(updated.status).toBe('completed');
    expect(updated.stopReason).toBeDefined();
  });
});

