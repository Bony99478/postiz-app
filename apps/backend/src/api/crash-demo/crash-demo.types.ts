export type CrashDemoProfile = 'conservative' | 'balanced' | 'aggressive';

export interface CrashDemoConfig {
  seed: string;
  volatility: number;
  roundFrequencyMs: number;
  durationRounds: number;
  initialBankroll: number;
  minBet: number;
  maxBet: number;
  maxMultiplier: number;
}

export interface CrashDemoAgentStrategy {
  profile: CrashDemoProfile;
  targetCashout: number;
  betFraction: number;
  stopLossPct: number;
  takeProfitPct: number;
}

export interface CrashDemoAgentDecision {
  betAmount: number;
  targetCashout: number;
  reason: string;
}

export interface CrashDemoRoundResult {
  round: number;
  crashMultiplier: number;
  betAmount: number;
  targetCashout: number;
  didWin: boolean;
  payout: number;
  pnl: number;
  bankrollAfterRound: number;
}

export interface CrashDemoStats {
  roundsPlayed: number;
  wins: number;
  losses: number;
  winRate: number;
  totalPnl: number;
  roiPct: number;
  peakBankroll: number;
  maxDrawdownPct: number;
  cashoutDistribution: Record<string, number>;
}

export interface CrashDemoSession {
  id: string;
  orgId: string;
  createdAt: string;
  endedAt?: string;
  status: 'running' | 'stopped' | 'completed';
  stopReason?: string;
  config: CrashDemoConfig;
  agent: CrashDemoAgentStrategy;
  rounds: CrashDemoRoundResult[];
  stats: CrashDemoStats;
  bankroll: number;
}

