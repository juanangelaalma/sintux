declare module '*.svg?react' {
  import type { ComponentType, SVGProps } from 'react';
  const content: ComponentType<SVGProps<SVGSVGElement>>;
  export default content;
}
