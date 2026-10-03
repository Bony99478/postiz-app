import { Metadata } from 'next';
import { CrashDemo } from '@gitroom/frontend/components/agents/crash-demo';

export const metadata: Metadata = {
  title: 'Postiz - Crash Demo',
  description: '',
};

export default async function Page() {
  return <CrashDemo />;
}

