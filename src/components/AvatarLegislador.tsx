import React, { useState } from 'react';
import { getInitials } from '../utils/formatters';

interface AvatarLegisladorProps {
  src?: string;
  nombre: string;
  size?: 'sm' | 'md' | 'lg' | 'xl';
  className?: string;
}

const SIZE_CLASSES: Record<NonNullable<AvatarLegisladorProps['size']>, string> = {
  sm: 'w-10 h-10 text-xs',
  md: 'w-12 h-12 text-sm', // 48x48px
  lg: 'w-16 h-16 text-base', // 64x64px
  xl: 'w-24 h-24 md:w-28 md:h-28 text-xl',
};

export const AvatarLegislador: React.FC<AvatarLegisladorProps> = ({
  src,
  nombre,
  size = 'lg',
  className = '',
}) => {
  const [imgError, setImgError] = useState(false);
  const isGenericPlaceholder =
    !src || src.includes('User_icon_2.svg') || imgError;

  const sizeClass = SIZE_CLASSES[size];

  if (isGenericPlaceholder) {
    return (
      <div
        className={`${sizeClass} rounded-full border-2 border-slate-200 bg-slate-100 text-slate-700 font-bold flex items-center justify-center shrink-0 select-none ${className}`}
        aria-label={nombre}
      >
        {getInitials(nombre)}
      </div>
    );
  }

  return (
    <img
      src={src}
      alt={`Fotografía oficial de ${nombre}`}
      loading="lazy"
      onError={() => setImgError(true)}
      className={`${sizeClass} rounded-full border-2 border-slate-200 object-cover bg-slate-100 shrink-0 ${className}`}
    />
  );
};
