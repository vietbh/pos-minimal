export type LoadingStatus = 'idle' | 'loading' | 'ready' | 'error';

export type LoadingState = {
	status: LoadingStatus;
	progress: number;
	error?: Error;
};

export const IDLE_LOADING_STATE: LoadingState = {
	status: 'idle',
	progress: 0
};
