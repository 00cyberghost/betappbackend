import type { ImgHTMLAttributes } from 'react';

type AppLogoIconProps = Omit<ImgHTMLAttributes<HTMLImageElement>, 'src' | 'alt'> & {
    alt?: string;
};

export default function AppLogoIcon({ alt = 'Focliq', ...props }: AppLogoIconProps) {
    return <img {...props} src="/favicon.svg" alt={alt} />;
}
