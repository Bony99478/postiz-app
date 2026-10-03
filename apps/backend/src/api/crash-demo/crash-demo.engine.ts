import {
  CrashDemoAgentDecision,
  CrashDemoAgentStrategy,
  CrashDemoConfig,
  CrashDemoRoundResult,
} from '@gitroom/backend/api/crash-demo/crash-demo.types';
import { SeededRandom } from '@gitroom/backend/api/crash-demo/seeded.random';

const clamp = (value: number, min: number, max: number) =>
  Math.min(max, Math.max(min, value));

export class CrashDemoEngine {
  private _random: SeededRandom;

  constructor(seed: string) {
    this._random = new SeededRandom(seed);
  }

  static getDefaultStrategy(profile: CrashDemoAgentStrategy['profile']) {
    if (profile === 'conservative') {
      return {
        profile,
        targetCashout: 1.35,
        betFraction: 0.015,
        stopLossPct: 0.2,
        takeProfitPct: 0.12,
      } satisfies CrashDemoAgentStrategy;
    }

    if (profile === 'aggressive') {
      return {
        profile,
        targetCashout: 2.4,
        betFraction: 0.06,
        stopLossPct: 0.45,
        takeProfitPct: 0.5,
      } satisfies CrashDemoAgentStrategy;
    }

    return {
      profile: 'balanced',
      targetCashout: 1.75,
      betFraction: 0.03,
      stopLossPct: 0.3,
      takeProfitPct: 0.25,
    } satisfies CrashDemoAgentStrategy;
  }

  generateCrashMultiplier(config: CrashDemoConfig): number {
    const u = clamp(this._random.next(), 0.000001, 0.999999);
    const volatility = clamp(config.volatility, 0.1, 5);
    const base = 1 / (1 - u);
    const scaled = 1 + (base - 1) * volatility;
    const withHouseEdge = scaled * 0.99;
    return Number(clamp(withHouseEdge, 1.01, config.maxMultiplier).toFixed(2));
  }

  buildDecision(
    bankroll: number,
    config: CrashDemoConfig,
    strategy: CrashDemoAgentStrategy
  ): CrashDemoAgentDecision {
    const proportionalBet = bankroll * strategy.betFraction;
    const betAmount = Number(
      clamp(proportionalBet, config.minBet, config.maxBet).toFixed(2)
    );

    return {
      betAmount,
      targetCashout: Number(strategy.targetCashout.toFixed(2)),
      reason: `${strategy.profile} profile`,
    };
  }

  playRound(input: {
    round: number;
    bankroll: number;
    config: CrashDemoConfig;
    strategy: CrashDemoAgentStrategy;
  }): CrashDemoRoundResult {
    const { round, bankroll, config, strategy } = input;
    const decision = this.buildDecision(bankroll, config, strategy);
    const crashMultiplier = this.generateCrashMultiplier(config);
    const didWin = crashMultiplier >= decision.targetCashout;
    const payout = didWin ? decision.betAmount * decision.targetCashout : 0;
    const pnl = Number((payout - decision.betAmount).toFixed(2));
    const bankrollAfterRound = Number((bankroll + pnl).toFixed(2));

    return {
      round,
      crashMultiplier,
      betAmount: decision.betAmount,
      targetCashout: decision.targetCashout,
      didWin,
      payout: Number(payout.toFixed(2)),
      pnl,
      bankrollAfterRound,
    };
  }
}

