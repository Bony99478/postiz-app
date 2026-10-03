'use client';

import { FormEvent, useMemo, useState } from 'react';
import { useFetch } from '@gitroom/helpers/utils/custom.fetch';
import { Button } from '@gitroom/react/form/button';

type CrashDemoResponse = {
  id: string;
  status: 'running' | 'stopped' | 'completed';
  stopReason?: string;
  bankroll: number;
  config: {
    seed: string;
    volatility: number;
    durationRounds: number;
    roundFrequencyMs: number;
  };
  agent: {
    profile: string;
    targetCashout: number;
    betFraction: number;
    stopLossPct: number;
    takeProfitPct: number;
  };
  rounds: Array<{
    round: number;
    crashMultiplier: number;
    betAmount: number;
    targetCashout: number;
    didWin: boolean;
    payout: number;
    pnl: number;
    bankrollAfterRound: number;
  }>;
  stats: {
    roundsPlayed: number;
    wins: number;
    losses: number;
    winRate: number;
    totalPnl: number;
    roiPct: number;
    peakBankroll: number;
    maxDrawdownPct: number;
    cashoutDistribution: Record<string, number>;
  };
};

const profileOptions = ['conservative', 'balanced', 'aggressive'] as const;

const buildPolyline = (values: number[]) => {
  if (!values.length) {
    return '';
  }
  const width = 640;
  const height = 180;
  const min = Math.min(...values);
  const max = Math.max(...values);
  const span = max - min || 1;
  return values
    .map((value, index) => {
      const x = (index / Math.max(values.length - 1, 1)) * width;
      const y = height - ((value - min) / span) * height;
      return `${x},${y}`;
    })
    .join(' ');
};

export const CrashDemo = () => {
  const fetch = useFetch();
  const [session, setSession] = useState<CrashDemoResponse | null>(null);
  const [loading, setLoading] = useState(false);
  const [runRounds, setRunRounds] = useState(25);
  const [config, setConfig] = useState({
    seed: '',
    profile: 'balanced',
    volatility: 1,
    durationRounds: 200,
    roundFrequencyMs: 1000,
    initialBankroll: 1000,
  });

  const bankrollSeries = useMemo(() => {
    if (!session) {
      return [];
    }
    return [config.initialBankroll, ...session.rounds.map((p) => p.bankrollAfterRound)];
  }, [session, config.initialBankroll]);

  const polyline = useMemo(() => buildPolyline(bankrollSeries), [bankrollSeries]);

  const startSimulation = async (e: FormEvent) => {
    e.preventDefault();
    setLoading(true);
    try {
      const response = await fetch('/crash-demo/start', {
        method: 'POST',
        body: JSON.stringify({
          seed: config.seed || undefined,
          profile: config.profile,
          volatility: Number(config.volatility),
          durationRounds: Number(config.durationRounds),
          roundFrequencyMs: Number(config.roundFrequencyMs),
          initialBankroll: Number(config.initialBankroll),
        }),
      });

      setSession(await response.json());
    } finally {
      setLoading(false);
    }
  };

  const run = async () => {
    if (!session) {
      return;
    }
    setLoading(true);
    try {
      const response = await fetch(`/crash-demo/${session.id}/run`, {
        method: 'POST',
        body: JSON.stringify({ rounds: Number(runRounds) }),
      });
      setSession(await response.json());
    } finally {
      setLoading(false);
    }
  };

  const stop = async () => {
    if (!session) {
      return;
    }
    setLoading(true);
    try {
      const response = await fetch(`/crash-demo/${session.id}/stop`, {
        method: 'POST',
      });
      setSession(await response.json());
    } finally {
      setLoading(false);
    }
  };

  const refresh = async () => {
    if (!session) {
      return;
    }
    const response = await fetch(`/crash-demo/${session.id}`);
    setSession(await response.json());
  };

  return (
    <div className="bg-newBgColorInner p-[20px] flex flex-1 flex-col gap-[16px] overflow-auto">
      <div className="rounded-[8px] border border-orange-500/50 bg-orange-500/10 p-[12px] text-[14px]">
        Cette page est une démonstration éducative uniquement. Elle ne prédit
        aucun résultat réel et ne doit pas être utilisée pour des décisions
        financières.
      </div>

      <form
        onSubmit={startSimulation}
        className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-[10px] bg-newBgColor p-[12px] rounded-[8px]"
      >
        <input
          className="bg-transparent border border-fifth rounded-[6px] px-[10px] py-[8px]"
          value={config.seed}
          onChange={(e) => setConfig({ ...config, seed: e.target.value })}
          placeholder="Seed (optionnel)"
        />
        <select
          className="bg-transparent border border-fifth rounded-[6px] px-[10px] py-[8px]"
          value={config.profile}
          onChange={(e) => setConfig({ ...config, profile: e.target.value })}
        >
          {profileOptions.map((profile) => (
            <option value={profile} key={profile}>
              {profile}
            </option>
          ))}
        </select>
        <input
          type="number"
          step="0.1"
          min={0.1}
          max={5}
          className="bg-transparent border border-fifth rounded-[6px] px-[10px] py-[8px]"
          value={config.volatility}
          onChange={(e) =>
            setConfig({ ...config, volatility: Number(e.target.value) })
          }
          placeholder="Volatilité"
        />
        <input
          type="number"
          min={1}
          className="bg-transparent border border-fifth rounded-[6px] px-[10px] py-[8px]"
          value={config.durationRounds}
          onChange={(e) =>
            setConfig({ ...config, durationRounds: Number(e.target.value) })
          }
          placeholder="Durée (rounds)"
        />
        <input
          type="number"
          min={10}
          className="bg-transparent border border-fifth rounded-[6px] px-[10px] py-[8px]"
          value={config.initialBankroll}
          onChange={(e) =>
            setConfig({ ...config, initialBankroll: Number(e.target.value) })
          }
          placeholder="Bankroll initiale"
        />
        <Button type="submit" disabled={loading}>
          Démarrer la simulation
        </Button>
      </form>

      {session && (
        <>
          <div className="flex flex-wrap gap-[10px] items-end bg-newBgColor p-[12px] rounded-[8px]">
            <div className="text-[14px]">
              <strong>Session:</strong> {session.id}
            </div>
            <div className="text-[14px]">
              <strong>Statut:</strong> {session.status}
              {session.stopReason ? ` (${session.stopReason})` : ''}
            </div>
            <div className="text-[14px]">
              <strong>Bankroll:</strong> {session.bankroll.toFixed(2)}
            </div>
            <input
              type="number"
              min={1}
              max={10000}
              className="bg-transparent border border-fifth rounded-[6px] px-[10px] py-[8px] w-[140px]"
              value={runRounds}
              onChange={(e) => setRunRounds(Number(e.target.value))}
            />
            <Button onClick={run} disabled={loading || session.status !== 'running'}>
              Exécuter des rounds
            </Button>
            <Button onClick={stop} disabled={loading || session.status !== 'running'}>
              Stop
            </Button>
            <Button onClick={refresh} disabled={loading}>
              Rafraîchir
            </Button>
          </div>

          <div className="grid grid-cols-2 md:grid-cols-4 gap-[10px]">
            <StatCard label="Rounds joués" value={session.stats.roundsPlayed} />
            <StatCard label="Win rate" value={`${session.stats.winRate}%`} />
            <StatCard label="PnL total" value={session.stats.totalPnl.toFixed(2)} />
            <StatCard label="ROI" value={`${session.stats.roiPct}%`} />
            <StatCard
              label="Max drawdown"
              value={`${session.stats.maxDrawdownPct}%`}
            />
            <StatCard
              label="Pic bankroll"
              value={session.stats.peakBankroll.toFixed(2)}
            />
            <StatCard label="Victoires" value={session.stats.wins} />
            <StatCard label="Défaites" value={session.stats.losses} />
          </div>

          <div className="bg-newBgColor p-[12px] rounded-[8px]">
            <div className="mb-[10px] font-[600]">Courbe bankroll</div>
            <svg viewBox="0 0 640 180" className="w-full h-[180px]">
              <polyline
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                points={polyline}
              />
            </svg>
          </div>

          <div className="bg-newBgColor p-[12px] rounded-[8px]">
            <div className="mb-[10px] font-[600]">Distribution des crashs</div>
            <div className="grid grid-cols-2 md:grid-cols-4 gap-[8px]">
              {Object.entries(session.stats.cashoutDistribution).map(([range, count]) => (
                <div key={range} className="border border-fifth rounded-[8px] p-[8px]">
                  <div className="text-[12px] text-textColor">{range}</div>
                  <div className="text-[18px] font-[600]">{count}</div>
                </div>
              ))}
            </div>
          </div>

          <div className="bg-newBgColor p-[12px] rounded-[8px]">
            <div className="mb-[10px] font-[600]">Décisions IA (derniers rounds)</div>
            <div className="overflow-auto">
              <table className="w-full text-left text-[13px]">
                <thead>
                  <tr className="border-b border-fifth">
                    <th className="py-[8px]">Round</th>
                    <th className="py-[8px]">Crash</th>
                    <th className="py-[8px]">Mise</th>
                    <th className="py-[8px]">Cashout cible</th>
                    <th className="py-[8px]">Résultat</th>
                    <th className="py-[8px]">PnL</th>
                    <th className="py-[8px]">Bankroll</th>
                  </tr>
                </thead>
                <tbody>
                  {[...session.rounds]
                    .slice(-20)
                    .reverse()
                    .map((round) => (
                      <tr key={round.round} className="border-b border-fifth/50">
                        <td className="py-[6px]">{round.round}</td>
                        <td className="py-[6px]">{round.crashMultiplier.toFixed(2)}x</td>
                        <td className="py-[6px]">{round.betAmount.toFixed(2)}</td>
                        <td className="py-[6px]">{round.targetCashout.toFixed(2)}x</td>
                        <td className="py-[6px]">{round.didWin ? 'Win' : 'Lose'}</td>
                        <td className="py-[6px]">{round.pnl.toFixed(2)}</td>
                        <td className="py-[6px]">
                          {round.bankrollAfterRound.toFixed(2)}
                        </td>
                      </tr>
                    ))}
                </tbody>
              </table>
            </div>
          </div>
        </>
      )}
    </div>
  );
};

const StatCard = ({ label, value }: { label: string; value: string | number }) => {
  return (
    <div className="bg-newBgColor rounded-[8px] p-[10px] border border-fifth">
      <div className="text-[12px] text-textColor">{label}</div>
      <div className="text-[20px] font-[600]">{value}</div>
    </div>
  );
};

