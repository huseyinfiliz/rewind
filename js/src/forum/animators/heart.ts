import { animate, stagger, random } from 'animejs';

export function animateHeart(root: Element, tl: any, key: string) {
  const num = root.querySelector('.rw-bignum');

  if (key === 'likes_received') {
    animateReceived(root);
  } else {
    animateGiven(root);
  }

  // Shared: count-up + pulse
  if (num) {
    tl.add(num, { opacity: [0, 1], scale: [3, 1], duration: 1200, ease: 'outElastic(1, .4)' }, 100);
    const target = parseInt(num.getAttribute('data-target') || '0');
    if (target > 0) {
      const obj = { val: 0 };
      animate(obj, {
        val: target,
        duration: 1800,
        ease: 'outExpo',
        onUpdate: () => {
          num.textContent = Math.round(obj.val).toLocaleString();
        },
        complete: () => {
          animate(num, {
            scale: [1, 1.08, 1],
            duration: 1200,
            loop: true,
            ease: 'easeInOutSine',
          });
        },
      });
    }
  }
}

/** likes_given: continuous outward drift from center, fade out at edges */
function animateGiven(root: Element) {
  const hearts = root.querySelectorAll('.rw-heart-burst span');
  if (hearts.length === 0) return;

  let stopped = false;

  const drift = (el: HTMLElement, initial: boolean) => {
    if (stopped) return;

    const dx = random(-300, 300);
    const dy = random(-300, 300);
    const sc = random(0.8, 2.2);
    const dur = initial ? random(2000, 3200) : random(3200, 5600);
    const startDelay = initial ? random(0, 800) : 0;

    animate(el, {
      translateX: [0, dx],
      translateY: [0, dy],
      scale: [0.2, sc, sc * 0.6],
      opacity: [0, 0.85, 0],
      rotateZ: [0, random(-120, 120)],
      duration: dur,
      delay: startDelay,
      ease: 'easeInOutSine',
    });

    // Schedule next cycle after this one finishes
    setTimeout(() => drift(el, false), startDelay + dur + 100);
  };

  hearts.forEach((el) => drift(el as HTMLElement, true));

  const observer = new MutationObserver(() => {
    if (!root.closest('.rw-slideshow')) {
      stopped = true;
      observer.disconnect();
    }
  });
  observer.observe(document.body, { childList: true, subtree: true });
}

/** likes_received: CSS-animated hearts floating up */
function animateReceived(root: Element) {
  const containerEl = root.querySelector('.rw-heart-stream');
  if (!containerEl) return;
  const container = containerEl;

  const colors = ['#ff6b6b', '#ee5a24', '#ff9ff3', '#f368e0', '#ff6348', '#e84393', '#fd79a8', '#ff4757', '#c44569'];
  const icons = ['fas fa-heart', 'fas fa-heart', 'fas fa-heart', 'far fa-heart'];

  let stopped = false;

  function spawnHeart(big?: boolean) {
    if (stopped) return;

    const el = document.createElement('div');
    el.className = 'rw-heart-float';
    el.innerHTML = `<i class="${icons[Math.floor(Math.random() * icons.length)]}"></i>`;

    const size = big ? random(28, 42) : random(12, 26);
    const scaleEnd = big ? random(1.2, 1.8) : random(0.7, 1.2);
    const dur = big ? random(4, 6) : random(3, 5);

    el.style.cssText = `
      left: ${random(5, 95)}%;
      color: ${colors[Math.floor(Math.random() * colors.length)]};
      font-size: ${size}px;
      animation: rwHeartRise ${dur}s ease-out forwards;
      --rw-drift: ${random(-50, 50)}px;
      --rw-scale: ${scaleEnd};
    `;

    container.appendChild(el);

    setTimeout(
      () => {
        if (container.contains(el)) container.removeChild(el);
      },
      dur * 1000 + 100
    );
  }

  // Burst: spawn several hearts at once
  function burst(count: number) {
    for (let i = 0; i < count; i++) {
      setTimeout(() => spawnHeart(i === 0), i * 60);
    }
  }

  // Initial big burst
  burst(10);

  // Continuous stream with periodic bursts
  let tickCount = 0;
  const tick = () => {
    if (stopped) return;
    tickCount++;
    if (tickCount % 8 === 0) {
      burst(random(3, 6));
    } else {
      spawnHeart(Math.random() < 0.15);
    }
    setTimeout(tick, random(180, 400));
  };
  setTimeout(tick, 1200);

  // Clean up when slide removed from DOM
  const observer = new MutationObserver(() => {
    if (!document.contains(container)) {
      stopped = true;
      observer.disconnect();
    }
  });
  observer.observe(document.body, { childList: true, subtree: true });
}
