import { CommonModule } from '@angular/common';
import { Component } from '@angular/core';
import { FormsModule } from '@angular/forms';
import spanishWords from 'an-array-of-spanish-words';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './app.component.html',
  styleUrl: './app.component.css'
})
export class AppComponent {
  readonly maxFallos = 7;
  readonly letrasTeclado = [
    'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M',
    'N', 'Ñ', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'
  ];
  readonly partes = ['cuerda', 'cabeza', 'tronco', 'brazo-izquierdo', 'brazo-derecho', 'pie-izquierdo', 'pie-derecho'];

  intento = '';
  mensaje = 'Pulsa nueva partida para empezar.';
  palabraOriginal = '';
  palabraNormalizada: string[] = [];
  letrasAdivinadas = new Set<string>();
  letrasFalladas = new Set<string>();
  juegoIniciado = false;

  constructor() {
    this.nuevaPartida();
  }

  get palabraOculta(): string[] {
    return this.palabraNormalizada.map((letra, indice) => {
      return this.letrasAdivinadas.has(letra) ? this.palabraOriginal[indice].toUpperCase() : '_';
    });
  }

  get letrasFalladasListado(): string {
    return [...this.letrasFalladas].sort().join(', ').toUpperCase();
  }

  get fallos(): number {
    return this.letrasFalladas.size;
  }

  get progreso(): number {
    return Math.round((this.letrasAdivinadas.size / new Set(this.palabraNormalizada).size) * 100);
  }

  get juegoGanado(): boolean {
    return this.palabraNormalizada.every((l) => this.letrasAdivinadas.has(l));
  }

  get juegoPerdido(): boolean {
    return this.fallos >= this.maxFallos;
  }

  get juegoTerminado(): boolean {
    return this.juegoGanado || this.juegoPerdido;
  }

  nuevaPartida(): void {
    this.palabraOriginal = this.obtenerPalabraValida();
    this.palabraNormalizada = [...this.palabraOriginal].map((letra) => this.normalizarLetra(letra));
    this.letrasAdivinadas.clear();
    this.letrasFalladas.clear();
    this.intento = '';
    this.juegoIniciado = true;
    this.mensaje = 'Juego iniciado. Escribe una letra y pulsa probar.';
  }

  probarIntento(): void {
    const letra = this.normalizarLetra(this.intento);
    this.intento = '';

    if (this.juegoTerminado) {
      return;
    }

    if (!/^[a-zñ]$/i.test(letra)) {
      this.mensaje = 'Introduce solo una letra valida.';
      return;
    }

    if (this.letrasAdivinadas.has(letra) || this.letrasFalladas.has(letra)) {
      this.mensaje = `La letra ${letra.toUpperCase()} ya fue usada.`;
      return;
    }

    if (this.palabraNormalizada.includes(letra)) {
      this.letrasAdivinadas.add(letra);
      this.mensaje = `Bien: ${letra.toUpperCase()} esta en la palabra.`;
    } else {
      this.letrasFalladas.add(letra);
      this.mensaje = `No aparece ${letra.toUpperCase()}.`;
    }

    if (this.juegoGanado) {
      this.mensaje = `Ganaste. La palabra era ${this.palabraOriginal.toUpperCase()}.`;
    }

    if (this.juegoPerdido) {
      this.mensaje = `Perdiste. La palabra era ${this.palabraOriginal.toUpperCase()}.`;
    }
  }

  probarDesdeTeclado(letra: string): void {
    this.intento = letra;
    this.probarIntento();
  }

  letraUsada(letra: string): boolean {
    const normalizada = letra.toLowerCase();
    return this.letrasAdivinadas.has(normalizada) || this.letrasFalladas.has(normalizada);
  }

  parteVisible(indice: number): boolean {
    return this.fallos > indice;
  }

  private obtenerPalabraValida(): string {
    let candidata = '';
    let intentos = 0;

    while (intentos < 8000) {
      intentos += 1;
      candidata = spanishWords[Math.floor(Math.random() * spanishWords.length)] ?? '';
      candidata = candidata.trim().toLowerCase();

      if (this.esPalabraJugable(candidata)) {
        return candidata;
      }
    }

    return 'ahorcado';
  }

  private esPalabraJugable(valor: string): boolean {
    if (valor.length < 4 || valor.length > 14) {
      return false;
    }

    return /^[a-záéíóúüñ]+$/i.test(valor);
  }

  private normalizarLetra(letra: string): string {
    return letra
      .normalize('NFD')
      .replace(/\p{Diacritic}/gu, '')
      .replace(/ü/gi, 'u')
      .toLowerCase();
  }
}
