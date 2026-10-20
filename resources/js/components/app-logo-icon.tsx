import type { SVGAttributes } from 'react';

/**
 * Catalogix mark: a product grid with one tile tagged.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path d="M3 4.5A1.5 1.5 0 0 1 4.5 3h5A1.5 1.5 0 0 1 11 4.5v5A1.5 1.5 0 0 1 9.5 11h-5A1.5 1.5 0 0 1 3 9.5v-5Zm10 0A1.5 1.5 0 0 1 14.5 3h5A1.5 1.5 0 0 1 21 4.5v5a1.5 1.5 0 0 1-1.5 1.5h-5A1.5 1.5 0 0 1 13 9.5v-5Zm-10 10A1.5 1.5 0 0 1 4.5 13h5a1.5 1.5 0 0 1 1.5 1.5v5A1.5 1.5 0 0 1 9.5 21h-5A1.5 1.5 0 0 1 3 19.5v-5Zm11.06-1.06A1.5 1.5 0 0 1 15.12 13h4.38A1.5 1.5 0 0 1 21 14.5v4.38a1.5 1.5 0 0 1-.44 1.06l-.62.62a1.5 1.5 0 0 1-2.12 0l-4.38-4.38a1.5 1.5 0 0 1 0-2.12l.62-.62ZM18 16.25a1.25 1.25 0 1 0 0-2.5 1.25 1.25 0 0 0 0 2.5Z" />
        </svg>
    );
}
