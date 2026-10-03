import {
  IsIn,
  IsInt,
  IsNumber,
  IsOptional,
  IsString,
  Max,
  Min,
} from 'class-validator';
import { Type } from 'class-transformer';
import { CrashDemoProfile } from '@gitroom/backend/api/crash-demo/crash-demo.types';

export class StartCrashDemoDto {
  @IsOptional()
  @IsString()
  seed?: string;

  @IsOptional()
  @Type(() => Number)
  @IsNumber()
  @Min(0.1)
  @Max(5)
  volatility?: number;

  @IsOptional()
  @Type(() => Number)
  @IsInt()
  @Min(50)
  @Max(60000)
  roundFrequencyMs?: number;

  @IsOptional()
  @Type(() => Number)
  @IsInt()
  @Min(1)
  @Max(100000)
  durationRounds?: number;

  @IsOptional()
  @Type(() => Number)
  @IsNumber()
  @Min(10)
  initialBankroll?: number;

  @IsOptional()
  @Type(() => Number)
  @IsNumber()
  @Min(0.1)
  minBet?: number;

  @IsOptional()
  @Type(() => Number)
  @IsNumber()
  @Min(1)
  maxBet?: number;

  @IsOptional()
  @Type(() => Number)
  @IsNumber()
  @Min(2)
  @Max(1000)
  maxMultiplier?: number;

  @IsOptional()
  @IsIn(['conservative', 'balanced', 'aggressive'])
  profile?: CrashDemoProfile;

  @IsOptional()
  @Type(() => Number)
  @IsNumber()
  @Min(1.01)
  @Max(50)
  targetCashout?: number;

  @IsOptional()
  @Type(() => Number)
  @IsNumber()
  @Min(0.001)
  @Max(1)
  betFraction?: number;

  @IsOptional()
  @Type(() => Number)
  @IsNumber()
  @Min(0.01)
  @Max(0.95)
  stopLossPct?: number;

  @IsOptional()
  @Type(() => Number)
  @IsNumber()
  @Min(0.01)
  @Max(5)
  takeProfitPct?: number;
}

export class RunCrashDemoDto {
  @Type(() => Number)
  @IsInt()
  @Min(1)
  @Max(10000)
  rounds!: number;
}

