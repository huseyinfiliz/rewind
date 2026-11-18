import { animate, stagger, random } from 'animejs';

export function animateWords(root: Element, tl: any) {
  const words = root.querySelectorAll('.rw-word-item');
  if (words.length > 0) {
    const rotations = [-8, 5, -3, 7, -6, 3, -4, 6];

    animate(words, {
      opacity: [0, 1],
      scale: () => [0, 1],
      translateZ: () => [random(400, 800), 0],
      rotateX: () => [random(-180, 180), 0],
      rotateY: () => [random(-180, 180), 0],
      rotateZ: (_el: unknown, i = 0) => {
        return [random(-90, 90), rotations[i % rotations.length] || 0];
      },
      delay: stagger(120, { start: 100 }),
      duration: 1500,
      ease: 'easeOutElastic(1, .6)',
    });

    // Continuous gentle sway — full round-trip keyframes
    setTimeout(() => {
      words.forEach((el, i) => {
        const baseRot = rotations[i % rotations.length] || 0;
        const dy = random(-5, 5);
        const dr = random(-2, 2);
        animate(el, {
          translateY: [0, dy, 0],
          rotateZ: [baseRot, baseRot + dr, baseRot],
          duration: random(3000, 5000),
          loop: true,
          ease: 'easeInOutSine',
        });
      });
    }, 2000);
  }
}
