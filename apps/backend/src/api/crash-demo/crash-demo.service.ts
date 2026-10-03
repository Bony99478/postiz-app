import { Injectable, NotFoundException } from '@nestjs/common';
import { randomUUID } from 'crypto';
import { CrashDemoEngine } from '@gitroom/backend/api/crash-demo/crash-demo.engine';
import {
  CrashDemoAgentStrategy,
  CrashDemoConfig,
  CrashDemoRoundResult,
  CrashDemoSession,
  CrashDemoStats,
} from '@gitroom/backend/api/crash-demo/crash-demo.types';

@Injectable()
export class CrashDemoService {
  private _sessions = new Map<string, CrashDemoSession>();
  private _engines = new Map<string, CrashDemoEngine>();

  startSession(input: {
    orgId: string;
    config: CrashDemoConfig;
    strategy: CrashDemoAgentStrategy;
  }) {
    const { orgId, config, strategy } = input;
    const id = randomUUID();
    const now = new Date().toISOString();
    const session: CrashDemoSession = {
      id,
      orgId,
      createdAt: now,
      status: 'running',
      config,
      agent: strategy,
      rounds: [],
      bankroll: config.initialBankroll,
      stats: {
        roundsPlayed: 0,
        wins: 0,
        losses: 0,
        winRate: 0,
        totalPnl: 0,
        roiPct: 0,
        peakBankroll: config.initialBankroll,
        maxDrawdownPct: 0,
        cashoutDistribution: {},
      },
    };

    this._sessions.set(id, session);
    this._engines.set(id, new CrashDemoEngine(config.seed));
    return session;
  }

  getSession(orgId: string, sessionId: string) {
    const session = this.requireSession(sessionId);
    this.checkOrg(orgId, session);
    return session;
  }

  stopSession(orgId: string, sessionId: string, reason = 'stopped_by_user') {
    const session = this.requireSession(sessionId);
    this.checkOrg(orgId, session);
    if (session.status === 'running') {
      session.status = 'stopped';
      session.stopReason = reason;
      session.endedAt = new Date().toISOString();
    }
    return session;
  }

  runRounds(orgId: string, sessionId: string, roundsToRun: number) {
    const session = this.requireSession(sessionId);
    this.checkOrg(orgId, session);
    const engine = this._engines.get(sessionId);
    if (!engine) {
      throw new NotFoundException('Simulation engine not found');
    }

    if (session.status !== 'running') {
      return session;
    }

    const safeRounds = Math.max(1, Math.min(10000, roundsToRun));
    for (let i = 0; i < safeRounds; i++) {
      if (this.shouldStopSession(session)) {
        break;
      }

      const roundNumber = session.rounds.length + 1;
      const round = engine.playRound({
        round: roundNumber,
        bankroll: session.bankroll,
        config: session.config,
        strategy: session.agent,
      });

      session.rounds.push(round);
      session.bankroll = round.bankrollAfterRound;
      session.stats = this.calculateStats(session);

      if (this.shouldStopSession(session)) {
        break;
      }
    }

    return session;
  }

  private shouldStopSession(session: CrashDemoSession) {
    const { rounds, config, bankroll, agent } = session;
    const initial = config.initialBankroll;
    const stopLossValue = initial * (1 - agent.stopLossPct);
    const takeProfitValue = initial * (1 + agent.takeProfitPct);

    if (rounds.length >= config.durationRounds) {
      session.status = 'completed';
      session.stopReason = 'duration_reached';
      session.endedAt = new Date().toISOString();
      return true;
    }

    if (bankroll <= stopLossValue) {
      session.status = 'completed';
      session.stopReason = 'stop_loss_reached';
      session.endedAt = new Date().toISOString();
      return true;
    }

    if (bankroll >= takeProfitValue) {
      session.status = 'completed';
      session.stopReason = 'take_profit_reached';
      session.endedAt = new Date().toISOString();
      return true;
    }

    if (bankroll < config.minBet) {
      session.status = 'completed';
      session.stopReason = 'bankroll_too_low';
      session.endedAt = new Date().toISOString();
      return true;
    }

    return false;
  }

  private calculateStats(session: CrashDemoSession): CrashDemoStats {
    const initial = session.config.initialBankroll;
    const rounds = session.rounds;
    const wins = rounds.filter((r) => r.didWin).length;
    const losses = rounds.length - wins;
    const totalPnl = Number((session.bankroll - initial).toFixed(2));
    const roiPct = Number(((totalPnl / initial) * 100).toFixed(2));
    const peakBankroll = Number(
      Math.max(initial, ...rounds.map((r) => r.bankrollAfterRound)).toFixed(2)
    );
    const maxDrawdownPct = this.maxDrawdownPct(rounds, initial);

    return {
      roundsPlayed: rounds.length,
      wins,
      losses,
      winRate: rounds.length
        ? Number(((wins / rounds.length) * 100).toFixed(2))
        : 0,
      totalPnl,
      roiPct,
      peakBankroll,
      maxDrawdownPct,
      cashoutDistribution: this.cashoutDistribution(rounds),
    };
  }

  private maxDrawdownPct(rounds: CrashDemoRoundResult[], initial: number) {
    let peak = initial;
    let maxDrawdown = 0;

    for (const round of rounds) {
      if (round.bankrollAfterRound > peak) {
        peak = round.bankrollAfterRound;
      }
      const drawdown = ((peak - round.bankrollAfterRound) / peak) * 100;
      if (drawdown > maxDrawdown) {
        maxDrawdown = drawdown;
      }
    }

    return Number(maxDrawdown.toFixed(2));
  }

  private cashoutDistribution(rounds: CrashDemoRoundResult[]) {
    const distribution: Record<string, number> = {
      '1.00-1.49': 0,
      '1.50-1.99': 0,
      '2.00-2.99': 0,
      '3.00+': 0,
    };

    rounds.forEach((round) => {
      const value = round.crashMultiplier;
      if (value < 1.5) {
        distribution['1.00-1.49'] += 1;
      } else if (value < 2) {
        distribution['1.50-1.99'] += 1;
      } else if (value < 3) {
        distribution['2.00-2.99'] += 1;
      } else {
        distribution['3.00+'] += 1;
      }
    });

    return distribution;
  }

  private requireSession(sessionId: string) {
    const session = this._sessions.get(sessionId);
    if (!session) {
      throw new NotFoundException('Simulation session not found');
    }
    return session;
  }

  private checkOrg(orgId: string, session: CrashDemoSession) {
    if (session.orgId !== orgId) {
      throw new NotFoundException('Simulation session not found');
    }
  }
}

