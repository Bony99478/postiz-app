import { Body, Controller, Get, Param, Post } from '@nestjs/common';
import { ApiTags } from '@nestjs/swagger';
import { GetOrgFromRequest } from '@gitroom/nestjs-libraries/user/org.from.request';
import { Organization } from '@prisma/client';
import {
  RunCrashDemoDto,
  StartCrashDemoDto,
} from '@gitroom/backend/api/dtos/crash-demo.dto';
import { CrashDemoService } from '@gitroom/backend/api/crash-demo/crash-demo.service';
import { CrashDemoEngine } from '@gitroom/backend/api/crash-demo/crash-demo.engine';

@ApiTags('Crash Demo')
@Controller('/crash-demo')
export class CrashDemoController {
  constructor(private _crashDemoService: CrashDemoService) {}

  @Post('/start')
  start(
    @GetOrgFromRequest() org: Organization,
    @Body() body: StartCrashDemoDto
  ) {
    const profile = body.profile || 'balanced';
    const defaults = CrashDemoEngine.getDefaultStrategy(profile);
    const seed = body.seed || `${org.id}-${Date.now()}`;
    const minBet = body.minBet ?? 1;
    const maxBet = Math.max(body.maxBet ?? 50, minBet);

    return this._crashDemoService.startSession({
      orgId: org.id,
      config: {
        seed,
        volatility: body.volatility ?? 1,
        roundFrequencyMs: body.roundFrequencyMs ?? 1000,
        durationRounds: body.durationRounds ?? 100,
        initialBankroll: body.initialBankroll ?? 1000,
        minBet,
        maxBet,
        maxMultiplier: body.maxMultiplier ?? 100,
      },
      strategy: {
        ...defaults,
        targetCashout: body.targetCashout ?? defaults.targetCashout,
        betFraction: body.betFraction ?? defaults.betFraction,
        stopLossPct: body.stopLossPct ?? defaults.stopLossPct,
        takeProfitPct: body.takeProfitPct ?? defaults.takeProfitPct,
      },
    });
  }

  @Post('/:sessionId/run')
  run(
    @GetOrgFromRequest() org: Organization,
    @Param('sessionId') sessionId: string,
    @Body() body: RunCrashDemoDto
  ) {
    return this._crashDemoService.runRounds(org.id, sessionId, body.rounds);
  }

  @Post('/:sessionId/stop')
  stop(
    @GetOrgFromRequest() org: Organization,
    @Param('sessionId') sessionId: string
  ) {
    return this._crashDemoService.stopSession(org.id, sessionId);
  }

  @Get('/:sessionId')
  get(
    @GetOrgFromRequest() org: Organization,
    @Param('sessionId') sessionId: string
  ) {
    return this._crashDemoService.getSession(org.id, sessionId);
  }
}

