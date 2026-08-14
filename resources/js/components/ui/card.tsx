import React from 'react';

export interface CardProps extends React.HTMLAttributes<HTMLDivElement> {
    className?: string;
}

export const Card = React.forwardRef<HTMLDivElement, CardProps>(
    ({ className = '', children, ...props }, ref) => {
        return (
            <div
                ref={ref}
                className={`rounded-xl border border-gray-200 bg-white p-5 shadow-xs dark:border-gray-800 dark:bg-gray-900 ${className}`}
                {...props}
            >
                {children}
            </div>
        );
    },
);

Card.displayName = 'Card';

export const CardHeader = React.forwardRef<HTMLDivElement, CardProps>(
    ({ className = '', children, ...props }, ref) => {
        return (
            <div ref={ref} className={`flex items-center justify-between ${className}`} {...props}>
                {children}
            </div>
        );
    },
);

CardHeader.displayName = 'CardHeader';

export const CardTitle = React.forwardRef<HTMLHeadingElement, React.HTMLAttributes<HTMLHeadingElement>>(
    ({ className = '', children, ...props }, ref) => {
        return (
            <h3 ref={ref} className={`text-sm font-semibold text-gray-700 dark:text-gray-300 ${className}`} {...props}>
                {children}
            </h3>
        );
    },
);

CardTitle.displayName = 'CardTitle';

export const CardContent = React.forwardRef<HTMLDivElement, CardProps>(
    ({ className = '', children, ...props }, ref) => {
        return (
            <div ref={ref} className={`mt-2 ${className}`} {...props}>
                {children}
            </div>
        );
    },
);

CardContent.displayName = 'CardContent';
