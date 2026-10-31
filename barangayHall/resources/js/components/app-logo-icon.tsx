import type { SVGAttributes } from 'react';

/**
 * Health center mark: a rounded medical cross with a heartbeat line.
 * Uses currentColor so it follows the surrounding text color.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            viewBox="0 0 32 32"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden
            {...props}
        >
            <path
                fill="currentColor"
                d="M12 3a2 2 0 0 0-2 2v5H5a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h5v5a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-5h5a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-5V5a2 2 0 0 0-2-2h-8Z"
                opacity=".9"
            />
            <path
                d="M6 16.5h5l2-4 3 8 2.5-6 1.5 2h6"
                fill="none"
                stroke="var(--logo-pulse, white)"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}
